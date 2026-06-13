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
 * AJAX repository for mod_coursemail.
 *
 * @module     mod_coursemail/repository
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function($, Ajax) {

    /**
     * Calls a single external function and returns its promise.
     *
     * @param {String} methodname The external function name.
     * @param {Object} args The arguments.
     * @return {Promise}
     */
    var request = function(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    };

    return {
        /**
         * Fetches the items of a folder.
         *
         * @param {Number} cmid Course module id.
         * @param {String} folder Folder key.
         * @param {Number} page Zero-based page number.
         * @param {String} scope Read scope: "activity" (default) or "course".
         * @return {Promise}
         */
        getFolder: function(cmid, folder, page, scope, filter) {
            return request('mod_coursemail_get_folder',
                {cmid: cmid, folder: folder, page: page || 0, scope: scope || 'activity', filter: filter || ''});
        },

        /**
         * Searches conversations by subject or body.
         *
         * @param {Number} cmid Course module id.
         * @param {String} query Search text.
         * @param {Number} page Zero-based page number.
         * @param {String} scope Read scope: "activity" (default) or "course".
         * @return {Promise}
         */
        searchMessages: function(cmid, query, page, scope) {
            return request('mod_coursemail_search_messages',
                {cmid: cmid, query: query, page: page || 0, scope: scope || 'activity'});
        },

        /**
         * Marks several conversations read or unread at once.
         *
         * @param {Number} cmid Course module id.
         * @param {Number[]} conversationids Conversation ids.
         * @param {Boolean} read True to mark read, false to mark unread.
         * @return {Promise}
         */
        bulkMark: function(cmid, conversationids, read) {
            return request('mod_coursemail_bulk_mark',
                {cmid: cmid, conversationids: conversationids, read: read});
        },

        /**
         * Fetches a conversation and marks it read.
         *
         * @param {Number} cmid Course module id.
         * @param {Number} conversationid Conversation id.
         * @return {Promise}
         */
        getConversation: function(cmid, conversationid) {
            return request('mod_coursemail_get_conversation', {cmid: cmid, conversationid: conversationid});
        },

        /**
         * Stars or unstars a message.
         *
         * @param {Number} cmid Course module id.
         * @param {Number} messageid Message id.
         * @param {Boolean} starred Whether to star.
         * @return {Promise}
         */
        toggleStarred: function(cmid, messageid, starred) {
            return request('mod_coursemail_toggle_starred',
                {cmid: cmid, messageid: messageid, starred: starred});
        },

        /**
         * Marks a conversation as unread again for the current user.
         *
         * @param {Number} cmid Course module id.
         * @param {Number} conversationid Conversation id.
         * @return {Promise}
         */
        markUnread: function(cmid, conversationid) {
            return request('mod_coursemail_mark_unread', {cmid: cmid, conversationid: conversationid});
        },

        /**
         * Fetches the composer recipient options.
         *
         * @param {Number} cmid Course module id.
         * @return {Promise}
         */
        getRecipients: function(cmid) {
            return request('mod_coursemail_get_recipients', {cmid: cmid});
        },

        /**
         * Fetches a draft for editing.
         *
         * @param {Number} cmid Course module id.
         * @param {Number} draftid Draft message id.
         * @return {Promise}
         */
        getDraft: function(cmid, draftid) {
            return request('mod_coursemail_get_draft', {cmid: cmid, draftid: draftid});
        },

        /**
         * Starts a new conversation.
         *
         * @param {Number} cmid Course module id.
         * @param {Object} data { subject, body, requiresresponse, recipienttype, recipientids }
         * @return {Promise}
         */
        startConversation: function(cmid, data) {
            return request('mod_coursemail_start_conversation', $.extend({cmid: cmid}, data));
        },

        /**
         * Sends a reply.
         *
         * @param {Number} cmid Course module id.
         * @param {Number} conversationid Conversation id.
         * @param {String} body Reply body.
         * @return {Promise}
         */
        reply: function(cmid, conversationid, body, draftitemid) {
            return request('mod_coursemail_reply',
                {cmid: cmid, conversationid: conversationid, body: body, draftitemid: draftitemid || 0});
        },

        /**
         * Saves a draft.
         *
         * @param {Number} cmid Course module id.
         * @param {Number} draftid Draft id (0 to create).
         * @param {String} subject Subject.
         * @param {String} body Body.
         * @return {Promise}
         */
        saveDraft: function(cmid, draftid, subject, body, draftitemid) {
            return request('mod_coursemail_save_draft',
                {cmid: cmid, draftid: draftid, subject: subject, body: body, draftitemid: draftitemid || 0});
        },

        /**
         * Sends an existing draft.
         *
         * @param {Number} cmid Course module id.
         * @param {Object} data { draftid, subject, body, requiresresponse, recipienttype, recipientids }
         * @return {Promise}
         */
        sendDraft: function(cmid, data) {
            return request('mod_coursemail_send_draft', $.extend({cmid: cmid}, data));
        },

        /**
         * Marks (or reopens) a student as manually completed in a conversation.
         *
         * @param {Number} cmid Course module id.
         * @param {Number} conversationid Conversation id.
         * @param {Number} userid Student user id.
         * @param {Boolean} completed True to mark complete, false to reopen.
         * @return {Promise}
         */
        setRecipientCompleted: function(cmid, conversationid, userid, completed) {
            return request('mod_coursemail_set_recipient_completed',
                {cmid: cmid, conversationid: conversationid, userid: userid, completed: completed});
        }
    };
});
