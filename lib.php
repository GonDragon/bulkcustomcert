<?php
// This file is part of the local_bulkcustomcert plugin for Moodle - http://moodle.org/
//
// local_bulkcustomcert is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// local_bulkcustomcert is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information for local_bulkcustomcert.
 *
 * @package    local_bulkcustomcert
 * @author     Gonzalo Romero
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


function local_bulkcustomcert_extend_settings_navigation($settingsnav, $context)
{
    global $DB, $CFG;

    $addnode = $context->contextlevel === 50;
    $addnode = $addnode && !($context->instanceid === SITEID);
    $addnode = $addnode && has_capability('mod/customcert:viewallcertificates', $context);

    if ($addnode) {

        //Check if there is available certs
        $certs = $DB->get_records('customcert', ['course' => $context->instanceid]);
        $users = $DB->get_records('role_assignments', ['contextid' => $context->id]);
        $availablecerts = false;

        foreach ($certs as $certid => $cert_fields) {
            foreach ($users as $userid => $user_fields) {
                if (!$DB->get_record('customcert_issues', ['userid' => $userid, 'customcertid' => $certid])) {
                    continue;
                }
                $availablecerts = true;
                break;
            }
            if ($availablecerts) break;
        }

        if ($availablecerts) {
            $id = $context->instanceid;
            $urltext = get_string('bulkdownloadlink', 'local_bulkcustomcert');
            $url = new moodle_url('/local/bulkcustomcert/index.php', [
                'id' => $id,
            ]);
            // Find the course settings node using the 'courseadmin' key.
            $coursesettingsnode = $settingsnav->find('courseadmin', null);
            $node = $coursesettingsnode->create(
                $urltext,
                $url,
                navigation_node::NODETYPE_LEAF,
                null,
                'bulkcustomcert',
                new pix_icon('a/download_all', 'certificates')
            );

            // Add the new node _before_ the 'gradebooksetup' node.
            $coursesettingsnode->add_node($node, 'gradebooksetup');
        }
    }
}
