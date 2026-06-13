// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mailbox controller for mod_coursemail.
 *
 * @module     mod_coursemail/mailbox
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/templates', 'core/notification', 'core/str', 'core/toast',
        'core/config', 'mod_coursemail/repository'],
function($, Templates, Notification, Str, Toast, Config, Repository) {

    var SELECTORS = {
        ROOT: '[data-region="coursemail-mailbox"]',
        CONTENT: '[data-region="content"]',
        READING: '[data-region="reading"]',
        ITEM: '[data-action="open-item"]',
        FOLDER_BTN: '[data-action="select-folder"]',
        SCOPE_BTN: '[data-action="select-scope"]',
        TOGGLE_NAV: '[data-action="toggle-nav"]',
        NAV_TOGGLE_ICON: '.coursemail-nav-toggle-icon',
        COMPOSE_BTN: '[data-action="compose"]',
        HELP_BTN: '[data-action="help"]',
        OPEN_ITEM: '[data-action="open-item"]',
        BACK: '[data-action="back"]',
        MARK_UNREAD: '[data-action="mark-unread"]',
        SUBJECT: '[data-region="conversation-subject"]',
        STAR: '[data-action="toggle-star"]',
        TOGGLE_COMPLETED: '[data-action="toggle-completed"]',
        SEND: '[data-action="send"]',
        SAVEDRAFT: '[data-action="savedraft"]',
        CANCEL: '[data-action="cancel"]',
        SEND_REPLY: '[data-action="send-reply"]',
        LOAD_MORE: '[data-action="loadmore"]',
        COMPOSE_FORM: '[data-region="compose"]',
        CONVERSATION: '[data-region="conversation"]',
        RECIPIENT_TYPE: 'input[name="recipienttype"]',
        SEARCH_FORM: '[data-region="search"]',
        SEARCH_INPUT: '[data-field="search"]',
        FILTERBAR: '[data-region="filterbar"]',
        FILTER_BTN: '[data-action="filter"]',
        BULKBAR: '[data-region="bulkbar"]',
        BULKCOUNT: '[data-region="bulkcount"]',
        BULK_BTN: '[data-action="bulk"]',
        BULK_CLEAR: '[data-action="bulk-clear"]',
        SELECT_ITEM: '[data-action="select-item"]',
        ITEM_ROW: '.coursemail-item-row'
    };

    // Marks the list as selectable (shows the per-row checkboxes).
    var SELECTABLE = 'coursemail-selectable';

    // Toggled on the root when a message/compose form occupies the reading pane.
    // On narrow screens the CSS uses it to swap the visible panel (list vs reading).
    var READING_OPEN = 'coursemail-reading-open';

    // Collapses the folder column to icons only (desktop). Persisted in localStorage.
    var NAV_COLLAPSED = 'coursemail-nav-collapsed';
    var NAV_PREF_KEY = 'mod_coursemail_navcollapsed';
    var navStrings = {collapse: '', expand: ''};

    // Read scope: 'activity' (this instance) or 'course' (every coursemail of the course).
    var SCOPE_PREF_KEY = 'mod_coursemail_scope';

    var cmid = 0;
    var root = null;
    var currentFolder = 'inbox';
    var currentPage = 0;
    var currentScope = 'activity';
    // When non-empty, the list shows search results instead of a folder.
    var currentQuery = '';
    // Active quick filter for the current folder ('' = none, 'unread', 'unanswered').
    var currentFilter = '';
    // Selected conversation ids for bulk actions, keyed by id.
    var selected = {};
    // Whether the scope toggle is offered (more than one readable coursemail in the course).
    var scopeAvailable = false;
    // Activities the user can compose in, for the unified-view target picker: [{cmid, name}].
    var composeTargets = [];
    // Whether the current user is staff (can send); selects the teacher vs student help text.
    var isStaff = false;
    // The instance the open conversation/draft belongs to; write actions route to it so that,
    // in the unified view, replying/starring hits the message's own activity, not the page's.
    var activeCmid = 0;
    var loadedItems = [];
    var loadingHtml = '';
    var readingEmptyHtml = '';
    // The list item whose message is open, so focus can return to it on close.
    var lastOpenedItem = null;

    /**
     * Renders a template into a given region.
     *
     * @param {String} regionSelector The target region selector.
     * @param {String} template Template name.
     * @param {Object} context Template context.
     * @param {(Boolean|String)} focus True to focus the region, or a selector within
     *        it to focus (falls back to the region). Falsy to leave focus alone.
     * @return {Promise}
     */
    var renderIntoRegion = function(regionSelector, template, context, focus) {
        var region = root.find(regionSelector);
        return Templates.render(template, context).then(function(html, js) {
            Templates.replaceNodeContents(region, html, js);
            if (focus) {
                var target = (typeof focus === 'string') ? region.find(focus).first() : $();
                if (target.length) {
                    target.trigger('focus');
                } else {
                    region.attr('tabindex', '-1').trigger('focus');
                }
            }
            return;
        }).catch(Notification.exception);
    };

    /**
     * Renders a template into the message-list region.
     *
     * @param {String} template Template name.
     * @param {Object} context Template context.
     * @param {(Boolean|String)} focus See renderIntoRegion.
     * @return {Promise}
     */
    var renderInto = function(template, context, focus) {
        return renderIntoRegion(SELECTORS.CONTENT, template, context, focus);
    };

    /**
     * Renders a template into the reading pane and marks it open.
     *
     * @param {String} template Template name.
     * @param {Object} context Template context.
     * @param {(Boolean|String)} focus See renderIntoRegion.
     * @return {Promise}
     */
    var renderReading = function(template, context, focus) {
        root.addClass(READING_OPEN);
        return renderIntoRegion(SELECTORS.READING, template, context, focus);
    };

    /**
     * Empties the reading pane and, on narrow screens, returns to the message list.
     *
     * @param {Boolean} returnFocus Whether to move focus back to the item that was open
     *        (used when the user explicitly goes back, not when switching folder).
     */
    var closeReading = function(returnFocus) {
        root.removeClass(READING_OPEN);
        root.find(SELECTORS.ITEM).removeClass('coursemail-item-active');
        if (readingEmptyHtml) {
            root.find(SELECTORS.READING).html(readingEmptyHtml);
        }
        if (returnFocus && lastOpenedItem && lastOpenedItem.closest('body').length) {
            lastOpenedItem.trigger('focus');
        }
        lastOpenedItem = null;
    };

    /**
     * Replaces the message-list region with a loading indicator.
     */
    var showLoading = function() {
        if (loadingHtml) {
            root.find(SELECTORS.CONTENT).html(loadingHtml);
        }
    };

    /**
     * Replaces the reading pane with a loading indicator.
     */
    var showReadingLoading = function() {
        root.addClass(READING_OPEN);
        if (loadingHtml) {
            root.find(SELECTORS.READING).html(loadingHtml);
        }
    };

    /**
     * Shows a transient toast message.
     *
     * @param {String} key String key in the coursemail component.
     */
    var toast = function(key) {
        Str.get_string(key, 'coursemail').then(function(text) {
            Toast.add(text);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Shows an error notification from a coursemail string key.
     *
     * @param {String} key String key in the coursemail component.
     */
    var notifyError = function(key) {
        Str.get_string(key, 'coursemail').then(function(text) {
            Notification.addNotification({message: text, type: 'error'});
            return;
        }).catch(Notification.exception);
    };

    /**
     * Renders the currently loaded folder items into the content region.
     *
     * @param {Boolean} hasmore Whether a further page exists.
     * @return {Promise}
     */
    var renderList = function(hasmore) {
        return renderInto('mod_coursemail/message_list', {
            items: loadedItems,
            hasitems: loadedItems.length > 0,
            hasmore: hasmore
        });
    };

    /**
     * Loads and renders the first page of a folder.
     *
     * @param {String} folder Folder key.
     */
    /**
     * Shows the quick-filter relevant to a folder and resets the active state.
     *
     * @param {String} folder Folder key.
     */
    var applyFilterbar = function(folder) {
        var bar = root.find(SELECTORS.FILTERBAR);
        bar.find(SELECTORS.FILTER_BTN).addClass('d-none').removeClass('active').attr('aria-pressed', 'false');
        if (folder === 'inbox') {
            bar.find('[data-filter="unread"]').removeClass('d-none');
            bar.removeClass('d-none');
        } else if (folder === 'all') {
            bar.find('[data-filter="unanswered"]').removeClass('d-none');
            bar.removeClass('d-none');
        } else {
            bar.addClass('d-none');
        }
    };

    /**
     * Reflects the active quick filter on the filter buttons.
     */
    var applyFilterActive = function() {
        root.find(SELECTORS.FILTER_BTN).each(function() {
            var on = $(this).attr('data-filter') === currentFilter && currentFilter !== '';
            $(this).toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
        });
    };

    /**
     * Updates the bulk-action bar from the current selection.
     */
    var updateBulkBar = function() {
        var ids = Object.keys(selected);
        var bar = root.find(SELECTORS.BULKBAR);
        if (ids.length > 0) {
            bar.addClass('coursemail-bulkbar-show');
            Str.get_string('selectedcount', 'coursemail', ids.length).then(function(text) {
                root.find(SELECTORS.BULKCOUNT).text(text);
                return;
            }).catch(Notification.exception);
        } else {
            bar.removeClass('coursemail-bulkbar-show');
        }
    };

    /**
     * Clears the current selection and hides the bulk bar.
     */
    var clearSelection = function() {
        selected = {};
        root.find(SELECTORS.SELECT_ITEM + ':checked').prop('checked', false);
        root.find(SELECTORS.ITEM_ROW).removeClass('coursemail-row-selected');
        updateBulkBar();
    };

    /**
     * Enables multi-select only where it is meaningful (Inbox, activity scope),
     * and clears any pending selection.
     */
    var refreshSelectable = function() {
        var selectable = (currentFolder === 'inbox' && currentScope === 'activity');
        root.toggleClass(SELECTABLE, selectable);
        clearSelection();
    };

    /**
     * (Re)loads the current folder's first page with the active scope and filter.
     */
    var fetchFolder = function() {
        currentPage = 0;
        refreshSelectable();
        closeReading();
        showLoading();
        Repository.getFolder(cmid, currentFolder, 0, currentScope, currentFilter).then(function(response) {
            loadedItems = response.items;
            return renderList(response.hasmore);
        }).catch(Notification.exception);
    };

    /**
     * Loads and renders the first page of a folder (resetting any search/filter).
     *
     * @param {String} folder Folder key.
     */
    var loadFolder = function(folder) {
        currentFolder = folder;
        currentFilter = '';
        currentQuery = '';
        root.find(SELECTORS.SEARCH_INPUT).val('');
        root.find(SELECTORS.FOLDER_BTN).removeClass('active');
        root.find('[data-folder="' + folder + '"]').addClass('active');
        applyFilterbar(folder);
        fetchFolder();
    };

    /**
     * Loads and renders the first page of search results for a query.
     *
     * @param {String} query The search text.
     */
    var loadSearch = function(query) {
        currentFolder = 'search';
        currentQuery = query;
        currentFilter = '';
        currentPage = 0;
        // Search is not one of the folders: clear the folder highlight and filters.
        root.find(SELECTORS.FOLDER_BTN).removeClass('active');
        root.find(SELECTORS.FILTERBAR).addClass('d-none');
        refreshSelectable();
        closeReading();
        showLoading();
        Repository.searchMessages(cmid, query, 0, currentScope).then(function(response) {
            loadedItems = response.items;
            return renderList(response.hasmore);
        }).catch(Notification.exception);
    };

    /**
     * Loads the next page of the current folder (or search) and appends it.
     */
    var loadMore = function() {
        currentPage += 1;
        var promise = (currentFolder === 'search')
            ? Repository.searchMessages(cmid, currentQuery, currentPage, currentScope)
            : Repository.getFolder(cmid, currentFolder, currentPage, currentScope, currentFilter);
        promise.then(function(response) {
            loadedItems = loadedItems.concat(response.items);
            return renderList(response.hasmore);
        }).catch(Notification.exception);
    };

    /**
     * Loads and renders a conversation.
     *
     * @param {Number} conversationid Conversation id.
     * @param {Number} itemcmid The cmid of the activity the conversation belongs to.
     */
    var loadConversation = function(conversationid, itemcmid) {
        activeCmid = itemcmid || cmid;
        showReadingLoading();
        Repository.getConversation(activeCmid, conversationid).then(function(conversation) {
            // Move focus to the subject heading for screen-reader / keyboard users.
            return renderReading('mod_coursemail/conversation', conversation, SELECTORS.SUBJECT);
        }).catch(Notification.exception);
    };

    /**
     * Marks the open conversation as unread again and returns to the folder list.
     *
     * @param {jQuery} button The mark-unread button.
     */
    var markUnread = function(button) {
        var conversationid = button.closest(SELECTORS.CONVERSATION).attr('data-conversationid');
        Repository.markUnread(activeCmid, conversationid).then(function() {
            toast('markedunread');
            // Reload the folder so the conversation shows as unread again.
            loadFolder(currentFolder);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Renders the compose form, optionally prefilled from a draft.
     *
     * @param {Object|null} draft Draft data { draftid, subject, body } or null.
     * @param {Number} [targetcmid] Activity to compose in; defaults to the page activity,
     *        or, in the unified view, the user's chosen target.
     */
    var openCompose = function(draft, targetcmid) {
        var editing = !!(draft && draft.draftid);
        // The target picker only applies to new messages in the unified view.
        var picker = (currentScope === 'course' && composeTargets.length > 0 && !editing);
        var target = targetcmid || cmid;
        if (picker && !targetcmid) {
            // Prefer the current activity when it is composable, otherwise the first target.
            var hascurrent = composeTargets.some(function(t) {
                return t.cmid === cmid;
            });
            target = hascurrent ? cmid : composeTargets[0].cmid;
        }
        activeCmid = target;
        showReadingLoading();
        Repository.getRecipients(target).then(function(options) {
            return renderReading('mod_coursemail/compose', {
                cansend: options.cansend,
                users: options.users,
                groups: options.groups,
                hasgroups: options.groups.length > 0,
                single: options.single,
                recipientname: options.recipientname,
                norecipients: options.norecipients,
                // Pre-tick the staff-only switches from the instance defaults (new messages only).
                requiresresponsedefault: !editing && options.requiresresponsedefault,
                requiremanualcompletedefault: !editing && options.requiremanualcompletedefault,
                draftid: draft ? draft.draftid : 0,
                draftitemid: draft ? draft.draftitemid : 0,
                attachments: draft ? draft.attachments : [],
                subject: draft ? draft.subject : '',
                body: draft ? draft.body : '',
                targetcmid: target,
                hasactivitypicker: picker,
                activities: picker ? composeTargets.map(function(t) {
                    return {cmid: t.cmid, name: t.name, selected: t.cmid === target};
                }) : []
            }, '[data-field="subject"]');
        }).catch(Notification.exception);
    };

    /**
     * Renders the role-aware help screen into the reading pane.
     */
    var openHelp = function() {
        renderReading('mod_coursemail/help', {isstaff: isStaff}, '[data-action="back"]')
            .catch(Notification.exception);
    };

    /**
     * Opens the composer prefilled from an existing draft.
     *
     * @param {Number} draftid Draft message id.
     * @param {Number} itemcmid The cmid of the activity the draft belongs to.
     */
    var editDraft = function(draftid, itemcmid) {
        var draftcmid = itemcmid || cmid;
        Repository.getDraft(draftcmid, draftid).then(function(draft) {
            openCompose(draft, draftcmid);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Reads the compose form into a data object.
     *
     * @param {jQuery} form The compose form.
     * @return {Object}
     */
    var gatherCompose = function(form) {
        var type = form.find(SELECTORS.RECIPIENT_TYPE + ':checked').val();
        if (!type) {
            type = form.find('[data-field="recipienttype"]').val() || 'staff';
        }

        var ids = [];
        if (type === 'users') {
            ids = form.find('[data-field="users"]').val() || [];
        } else if (type === 'group') {
            ids = form.find('[data-field="groups"]').val() || [];
        } else if (type === 'staffselected') {
            ids = form.find('[data-field="staffusers"]').val() || [];
        }

        return {
            draftid: parseInt(form.attr('data-draftid'), 10) || 0,
            draftitemid: parseInt(form.find('[data-field="draftitemid"]').val(), 10) || 0,
            subject: form.find('[data-field="subject"]').val(),
            body: form.find('[data-field="body"]').val(),
            recipienttype: type,
            recipientids: ids.map(function(id) {
                return parseInt(id, 10);
            }),
            requiresresponse: form.find('[data-field="requiresresponse"]').prop('checked') || false,
            requiresmanualcomplete: form.find('[data-field="requiresmanualcomplete"]').prop('checked') || false
        };
    };

    /**
     * Re-renders the staged-attachment list inside an attachments region.
     *
     * @param {jQuery} group The [data-region="attachments"] element.
     * @param {Array} files Staged files ({filename, size}).
     */
    var renderAttachments = function(group, files) {
        var list = group.find('[data-region="attachment-list"]');
        list.empty();
        (files || []).forEach(function(file) {
            var li = $('<li class="coursemail-attachment d-flex align-items-center mb-1"></li>')
                .attr('data-filename', file.filename);
            li.append('<i class="icon fa fa-paperclip m-0 mr-1" aria-hidden="true"></i>');
            li.append($('<span class="mr-2"></span>').text(file.filename));
            li.append($('<button type="button" class="btn btn-sm btn-link text-danger p-0"'
                + ' data-action="remove-attachment"></button>')
                .attr('data-filename', file.filename)
                .append('<i class="icon fa fa-times m-0" aria-hidden="true"></i>'));
            list.append(li);
        });
    };

    /**
     * Returns the activity cmid an attachments region should upload against.
     *
     * @param {jQuery} group The attachments region.
     * @return {Number}
     */
    var attachmentCmid = function(group) {
        var form = group.closest(SELECTORS.COMPOSE_FORM);
        return form.length ? (parseInt(form.attr('data-targetcmid'), 10) || cmid) : activeCmid;
    };

    /**
     * Uploads a queue of files sequentially to the draft area of an attachments region.
     *
     * @param {Array} files Remaining File objects.
     * @param {jQuery} group The attachments region.
     */
    var uploadFiles = function(files, group) {
        if (!files.length) {
            return;
        }
        var file = files.shift();
        var fd = new FormData();
        fd.append('cmid', attachmentCmid(group));
        fd.append('sesskey', Config.sesskey);
        fd.append('draftitemid', parseInt(group.find('[data-field="draftitemid"]').val(), 10) || 0);
        fd.append('attachment', file);
        $.ajax({
            url: Config.wwwroot + '/mod/coursemail/upload.php',
            type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
        }).done(function(resp) {
            group.find('[data-field="draftitemid"]').val(resp.draftitemid);
            renderAttachments(group, resp.files);
            uploadFiles(files, group);
        }).fail(function(xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : null;
            if (msg) {
                Notification.addNotification({message: msg, type: 'error'});
            } else {
                notifyError('uploadfailed');
            }
            uploadFiles(files, group);
        });
    };

    /**
     * Returns the activity a compose form targets (the page activity, or the chosen
     * one in the unified view).
     *
     * @param {jQuery} form The compose form.
     * @return {Number}
     */
    var composeCmid = function(form) {
        return parseInt(form.attr('data-targetcmid'), 10) || cmid;
    };

    /**
     * Sends the compose form (new conversation or saved draft).
     *
     * @param {jQuery} form The compose form.
     */
    var sendCompose = function(form) {
        var data = gatherCompose(form);
        if (!data.subject || !data.subject.trim()) {
            notifyError('nosubject');
            form.find('[data-field="subject"]').trigger('focus');
            return;
        }
        if (!data.body || !data.body.trim()) {
            notifyError('nobody');
            form.find('[data-field="body"]').trigger('focus');
            return;
        }
        var target = composeCmid(form);
        var promise;
        if (data.draftid) {
            // send_draft accepts draftid; new conversations do not.
            promise = Repository.sendDraft(target, data);
        } else {
            promise = Repository.startConversation(target, {
                subject: data.subject,
                body: data.body,
                requiresresponse: data.requiresresponse,
                requiresmanualcomplete: data.requiresmanualcomplete,
                recipienttype: data.recipienttype,
                recipientids: data.recipientids,
                draftitemid: data.draftitemid
            });
        }

        promise.then(function() {
            toast('messagesent');
            loadFolder('sent');
            return;
        }).catch(Notification.exception);
    };

    /**
     * Saves the compose form as a draft.
     *
     * @param {jQuery} form The compose form.
     */
    var saveDraft = function(form) {
        var data = gatherCompose(form);
        Repository.saveDraft(composeCmid(form), data.draftid, data.subject, data.body, data.draftitemid)
            .then(function(result) {
                form.attr('data-draftid', result.draftid);
                toast('draftsaved');
                return;
            }).catch(Notification.exception);
    };

    /**
     * Sends a reply within the open conversation.
     *
     * @param {jQuery} button The reply button.
     */
    var sendReply = function(button) {
        var conversation = button.closest(SELECTORS.CONVERSATION);
        var conversationid = conversation.attr('data-conversationid');
        var replyfield = conversation.find('[data-field="reply-body"]');
        var body = replyfield.val();
        if (!body || !body.trim()) {
            notifyError('emptyreply');
            replyfield.trigger('focus');
            return;
        }
        var draftitemid = parseInt(
            conversation.find('[data-region="attachments"] [data-field="draftitemid"]').val(), 10) || 0;
        Repository.reply(activeCmid, conversationid, body, draftitemid).then(function() {
            toast('replysent');
            loadConversation(conversationid, activeCmid);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Toggles the starred state of a message.
     *
     * @param {jQuery} button The star button.
     */
    var toggleStar = function(button) {
        var messageid = button.attr('data-messageid');
        var starred = button.attr('aria-pressed') !== 'true';
        Repository.toggleStarred(activeCmid, messageid, starred).then(function(result) {
            button.attr('aria-pressed', result.starred ? 'true' : 'false');
            var icon = button.find('i');
            icon.toggleClass('fa-star text-warning', result.starred);
            icon.toggleClass('fa-star-o', !result.starred);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Marks (or reopens) a student as manually completed, then refreshes the thread.
     *
     * @param {jQuery} button The toggle-completed button inside a recipient chip.
     */
    var toggleCompleted = function(button) {
        var userid = parseInt(button.attr('data-userid'), 10);
        var completed = button.attr('aria-pressed') !== 'true';
        var conversationid = button.closest(SELECTORS.CONVERSATION).attr('data-conversationid');
        Repository.setRecipientCompleted(activeCmid, conversationid, userid, completed).then(function() {
            // Re-render the thread so the chip's marker, button and border reflect the change.
            loadConversation(conversationid, activeCmid);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Applies the folder-navigation collapsed state to the DOM and toggle button.
     *
     * @param {Boolean} collapsed Whether the folder column should be collapsed.
     */
    var applyNavState = function(collapsed) {
        root.toggleClass(NAV_COLLAPSED, collapsed);
        var btn = root.find(SELECTORS.TOGGLE_NAV);
        btn.attr('aria-expanded', collapsed ? 'false' : 'true');
        var label = collapsed ? navStrings.expand : navStrings.collapse;
        if (label) {
            btn.attr('aria-label', label).attr('title', label);
        }
        var icon = btn.find(SELECTORS.NAV_TOGGLE_ICON);
        icon.toggleClass('fa-angle-double-left', !collapsed);
        icon.toggleClass('fa-angle-double-right', collapsed);
    };

    /**
     * Reads the stored folder-navigation preference (defaults to expanded).
     *
     * @return {Boolean} True if the column should start collapsed.
     */
    var readNavPref = function() {
        try {
            return window.localStorage.getItem(NAV_PREF_KEY) === '1';
        } catch (e) {
            return false;
        }
    };

    /**
     * Persists the folder-navigation preference.
     *
     * @param {Boolean} collapsed Whether the column is collapsed.
     */
    var writeNavPref = function(collapsed) {
        try {
            window.localStorage.setItem(NAV_PREF_KEY, collapsed ? '1' : '0');
        } catch (e) {
            // Storage unavailable (e.g. private mode): the choice just will not persist.
        }
    };

    /**
     * Reflects the active scope on the toggle buttons.
     *
     * @param {String} scope Either 'activity' or 'course'.
     */
    var applyScopeState = function(scope) {
        root.find(SELECTORS.SCOPE_BTN).each(function() {
            var btn = $(this);
            var on = btn.attr('data-scope') === scope;
            btn.toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
        });
    };

    /**
     * Reads the stored scope preference (defaults to 'activity').
     *
     * @return {String}
     */
    var readScopePref = function() {
        try {
            return window.localStorage.getItem(SCOPE_PREF_KEY) === 'course' ? 'course' : 'activity';
        } catch (e) {
            return 'activity';
        }
    };

    /**
     * Persists the scope preference.
     *
     * @param {String} scope Either 'activity' or 'course'.
     */
    var writeScopePref = function(scope) {
        try {
            window.localStorage.setItem(SCOPE_PREF_KEY, scope);
        } catch (e) {
            // Storage unavailable: the choice just will not persist.
        }
    };

    /**
     * Registers the delegated event handlers.
     */
    var registerEvents = function() {
        root.on('click', SELECTORS.FOLDER_BTN, function() {
            loadFolder($(this).attr('data-folder'));
        });
        root.on('click', SELECTORS.SCOPE_BTN, function() {
            var scope = $(this).attr('data-scope');
            if (scope === currentScope) {
                return;
            }
            currentScope = scope;
            applyScopeState(scope);
            writeScopePref(scope);
            // Re-run the active search, or reload the current folder (keeping its
            // filter), under the new scope.
            if (currentFolder === 'search' && currentQuery) {
                loadSearch(currentQuery);
            } else {
                if (!currentFolder) {
                    currentFolder = 'inbox';
                }
                fetchFolder();
            }
        });
        root.on('click', SELECTORS.FILTER_BTN, function() {
            var filter = $(this).attr('data-filter');
            // Toggle the filter off if it is already active.
            currentFilter = (currentFilter === filter) ? '' : filter;
            applyFilterActive();
            fetchFolder();
        });
        root.on('change', SELECTORS.SELECT_ITEM, function() {
            var cb = $(this);
            var id = cb.attr('data-conversationid');
            var row = cb.closest(SELECTORS.ITEM_ROW);
            if (cb.is(':checked')) {
                selected[id] = true;
                row.addClass('coursemail-row-selected');
            } else {
                delete selected[id];
                row.removeClass('coursemail-row-selected');
            }
            updateBulkBar();
        });
        root.on('click', SELECTORS.BULK_BTN, function() {
            var read = $(this).attr('data-read') === '1';
            var ids = Object.keys(selected).map(function(x) {
                return parseInt(x, 10);
            });
            if (!ids.length) {
                return;
            }
            Repository.bulkMark(cmid, ids, read).then(function() {
                // Reload to reflect the new read state (also clears the selection).
                fetchFolder();
                return;
            }).catch(Notification.exception);
        });
        root.on('click', SELECTORS.BULK_CLEAR, function() {
            clearSelection();
        });
        root.on('change', '[data-field="attachment-input"]', function() {
            var group = $(this).closest('[data-region="attachments"]');
            var files = Array.prototype.slice.call(this.files || []);
            this.value = '';
            uploadFiles(files, group);
        });
        root.on('click', '[data-action="remove-attachment"]', function() {
            var group = $(this).closest('[data-region="attachments"]');
            var draftitemid = parseInt(group.find('[data-field="draftitemid"]').val(), 10) || 0;
            if (!draftitemid) {
                return;
            }
            var fd = new FormData();
            fd.append('cmid', attachmentCmid(group));
            fd.append('sesskey', Config.sesskey);
            fd.append('draftitemid', draftitemid);
            fd.append('action', 'remove');
            fd.append('filename', $(this).attr('data-filename'));
            $.ajax({
                url: Config.wwwroot + '/mod/coursemail/upload.php',
                type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
            }).done(function(resp) {
                renderAttachments(group, resp.files);
            }).fail(function() {
                notifyError('uploadfailed');
            });
        });
        root.on('submit', SELECTORS.SEARCH_FORM, function(e) {
            e.preventDefault();
            var query = $.trim(root.find(SELECTORS.SEARCH_INPUT).val());
            // An empty query just returns to the inbox.
            if (query === '') {
                loadFolder('inbox');
            } else {
                loadSearch(query);
            }
        });
        root.on('click', SELECTORS.COMPOSE_BTN, function() {
            openCompose(null);
        });
        root.on('click', SELECTORS.HELP_BTN, function() {
            openHelp();
        });
        root.on('click', SELECTORS.TOGGLE_NAV, function() {
            var collapsed = !root.hasClass(NAV_COLLAPSED);
            applyNavState(collapsed);
            writeNavPref(collapsed);
        });
        root.on('click', SELECTORS.OPEN_ITEM, function() {
            var el = $(this);
            // The item carries its own activity cmid so write actions route there even
            // when the unified view mixes activities; falls back to the page activity.
            var srccmid = parseInt(el.attr('data-sourcecmid'), 10) || cmid;
            if (el.attr('data-draft') === '1') {
                editDraft(el.attr('data-messageid'), srccmid);
                return;
            }
            // Highlight the open item and reflect that it has just been read.
            root.find(SELECTORS.ITEM).removeClass('coursemail-item-active');
            el.addClass('coursemail-item-active').removeClass('font-weight-bold');
            el.find('.badge-primary').remove();
            lastOpenedItem = el;
            loadConversation(el.attr('data-conversationid'), srccmid);
        });
        root.on('click', SELECTORS.BACK, function() {
            closeReading(true);
        });
        root.on('click', SELECTORS.MARK_UNREAD, function() {
            markUnread($(this));
        });
        // Keyboard: Escape closes the open message; "/" focuses the search box.
        // Both are ignored while the user is typing in a field.
        root.on('keydown', function(e) {
            var tag = (e.target.tagName || '').toLowerCase();
            var typing = (tag === 'textarea' || tag === 'input' || tag === 'select');
            if ((e.key === 'Escape' || e.keyCode === 27) && root.hasClass(READING_OPEN) && !typing) {
                closeReading(true);
            } else if ((e.key === '/' || e.keyCode === 191) && !typing) {
                e.preventDefault();
                root.find(SELECTORS.SEARCH_INPUT).trigger('focus');
            }
        });
        root.on('click', SELECTORS.STAR, function(e) {
            e.stopPropagation();
            toggleStar($(this));
        });
        root.on('click', SELECTORS.TOGGLE_COMPLETED, function(e) {
            e.stopPropagation();
            toggleCompleted($(this));
        });
        root.on('click', SELECTORS.SEND, function() {
            sendCompose($(this).closest(SELECTORS.COMPOSE_FORM));
        });
        root.on('click', SELECTORS.SAVEDRAFT, function() {
            saveDraft($(this).closest(SELECTORS.COMPOSE_FORM));
        });
        root.on('click', SELECTORS.CANCEL, function() {
            closeReading();
        });
        root.on('click', SELECTORS.SEND_REPLY, function() {
            sendReply($(this));
        });
        root.on('click', SELECTORS.LOAD_MORE, function() {
            loadMore();
        });
        root.on('keydown', '[data-field="body"], [data-field="reply-body"]', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'Enter' || e.keyCode === 13)) {
                e.preventDefault();
                var field = $(this);
                if (field.attr('data-field') === 'reply-body') {
                    sendReply(field.closest(SELECTORS.CONVERSATION).find(SELECTORS.SEND_REPLY));
                } else {
                    sendCompose(field.closest(SELECTORS.COMPOSE_FORM));
                }
            }
        });
        root.on('change', SELECTORS.RECIPIENT_TYPE, function() {
            var form = $(this).closest(SELECTORS.COMPOSE_FORM);
            var type = $(this).val();
            form.find('[data-field="users"]').toggleClass('d-none', type !== 'users');
            form.find('[data-field="groups"]').toggleClass('d-none', type !== 'group');
            form.find('[data-field="staffusers"]').toggleClass('d-none', type !== 'staffselected');
        });
        root.on('change', '[data-field="activity"]', function() {
            // Switching the target activity reloads its recipients; preserve typed text.
            var form = $(this).closest(SELECTORS.COMPOSE_FORM);
            openCompose({
                draftid: 0,
                subject: form.find('[data-field="subject"]').val(),
                body: form.find('[data-field="body"]').val()
            }, parseInt($(this).val(), 10));
        });
    };

    return {
        /**
         * Initialises the mailbox.
         *
         * @param {(Object|Number)} config { cmid, scopeavailable, composetargets, isstaff }, or a
         *        bare cmid for backwards compatibility.
         */
        init: function(config) {
            if (config !== null && typeof config === 'object') {
                cmid = config.cmid;
                scopeAvailable = !!config.scopeavailable;
                composeTargets = config.composetargets || [];
                isStaff = !!config.isstaff;
            } else {
                cmid = config;
            }
            activeCmid = cmid;
            root = $(SELECTORS.ROOT);
            readingEmptyHtml = root.find(SELECTORS.READING).html();
            registerEvents();
            // Restore the saved scope before the first load (only if the toggle is offered).
            currentScope = scopeAvailable ? readScopePref() : 'activity';
            applyScopeState(currentScope);
            // Restore the folder-navigation state at once (avoids a layout flash),
            // then refresh the toggle's labels when the strings resolve.
            applyNavState(readNavPref());
            Str.get_strings([
                {key: 'collapsenav', component: 'coursemail'},
                {key: 'expandnav', component: 'coursemail'}
            ]).then(function(strings) {
                navStrings.collapse = strings[0];
                navStrings.expand = strings[1];
                applyNavState(root.hasClass(NAV_COLLAPSED));
                return;
            }).catch(Notification.exception);
            Str.get_string('loading', 'core').then(function(text) {
                loadingHtml = '<div class="text-center p-4" data-region="loading">'
                    + '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> '
                    + text + '</div>';
                return;
            }).catch(Notification.exception);
            loadFolder('inbox');
        }
    };
});
