<?php
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

namespace mod_coursemail\output;

use renderable;
use templatable;
use renderer_base;

/**
 * Renderable for the mailbox page shell (folder navigation + content region).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mailbox_page implements renderable, templatable {
    /** @var int Course module id. */
    protected $cmid;

    /** @var bool Whether the current user can start/reply to conversations. */
    protected $cancompose;

    /** @var string|null Custom label for the compose button (null = default "Compose"). */
    protected $composelabel;

    /** @var bool Whether the activity/course scope toggle should be offered. */
    protected $scopeavailable;

    /** @var bool Whether to offer the supervision folder (viewall capability). */
    protected $canviewall;

    /**
     * Constructor.
     *
     * @param int $cmid Course module id.
     * @param bool $cancompose Whether the current user can compose messages.
     * @param string|null $composelabel Custom compose button label, or null for the default.
     * @param bool $scopeavailable Whether to show the activity/course scope toggle.
     * @param bool $canviewall Whether to offer the supervision folder.
     */
    public function __construct(
        $cmid,
        $cancompose,
        $composelabel = null,
        $scopeavailable = false,
        $canviewall = false
    ) {
        $this->cmid = $cmid;
        $this->cancompose = $cancompose;
        $this->composelabel = $composelabel;
        $this->scopeavailable = $scopeavailable;
        $this->canviewall = $canviewall;
    }

    /**
     * Exports the data for the mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $icons = [
            'inbox' => 'fa-inbox',
            'sent' => 'fa-paper-plane',
            'drafts' => 'fa-file-o',
            'starred' => 'fa-star',
        ];
        $keys = ['inbox', 'sent', 'drafts', 'starred'];
        // The supervision folder lists every conversation; only staff with viewall see it.
        if ($this->canviewall) {
            $keys[] = 'all';
            $icons['all'] = 'fa-eye';
        }
        $folders = [];
        foreach ($keys as $key) {
            $folders[] = [
                'key' => $key,
                'name' => get_string($key === 'all' ? 'supervision' : $key, 'coursemail'),
                'icon' => $icons[$key],
                'active' => ($key === 'inbox'),
            ];
        }

        return [
            'cmid' => $this->cmid,
            'cancompose' => $this->cancompose,
            'composelabel' => $this->composelabel,
            'scopeavailable' => $this->scopeavailable,
            'folders' => $folders,
        ];
    }
}
