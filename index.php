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

// defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');

$courseid = optional_param('id', null, PARAM_INT);

if ($courseid) {
    assign_process_group_deleted_in_course_custom($courseid);
}

function assign_process_group_deleted_in_course_custom($courseid)
{
    if (!has_capability('mod/customcert:viewallcertificates', context_system::instance())) {
        die();
    }
    global $DB, $CFG;

    require_once($CFG->libdir . '/filelib.php');
    // Increase the server timeout to handle the creation and sending of large zip files.
    core_php_time_limit::raise();

    $course = $DB->get_record('course', ['id' => $courseid]);
    $certs = $DB->get_records('customcert', ['course' => $courseid]);
    $context = $DB->get_record('context', ['contextlevel' => '50', 'instanceid' => $courseid]);
    $users = $DB->get_records('role_assignments', ['contextid' => $context->id]);

    // Build a list of files to zip.
    $filesforzipping = array();
    $fs = get_file_storage();

    foreach ($certs as $certid => $cert_fields) {
        foreach ($users as $userid => $user_fields) {

            if (!$DB->get_record('customcert_issues', ['userid' => $userid, 'customcertid' => $certid])) {
                continue;
            }
            $user_info = $DB->get_record('user', ['id' => $user_fields->id]);

            $template = $DB->get_record('customcert_templates', array('id' => $cert_fields->templateid), '*', MUST_EXIST);
            $template = new \mod_customcert\template($template);
            $pdf = $template->generate_pdf(false, $userid, true);



            // Prepare file record object
            $fileinfo = array(
                'contextid' => $context->id, // ID of context
                'component' => 'mod_customcert',     // usually = table name
                'filearea' => 'customcert_issues',     // usually = table name
                'itemid' => $certid,               // usually = ID of row in table
                'filepath' => '/',           // any path beginning and ending in /
                'filename' => $user_info->username . '_cert-' . $certid . '_course-' . $course->shortname . '.pdf'
            ); // any filename

            if ($file = $fs->get_file(
                $fileinfo['contextid'],
                $fileinfo['component'],
                $fileinfo['filearea'],
                $fileinfo['itemid'],
                $fileinfo['filepath'],
                $fileinfo['filename']
            )) {
                $file->delete();
            }

            // Create file containing the pdf
            $fs->create_file_from_string($fileinfo, $pdf);

            $file = $fs->get_file(
                $fileinfo['contextid'],
                $fileinfo['component'],
                $fileinfo['filearea'],
                $fileinfo['itemid'],
                $fileinfo['filepath'],
                $fileinfo['filename']
            );

            $filesforzipping[$fileinfo['filepath'] . $fileinfo['filename']] = $file;
        }
    }

    $result = '';
    if (count($filesforzipping) == 0) {
        // This should never happen. The option only show up if there is available certs.
        $url = new moodle_url('/course/view.php?id=' . $courseid);
        redirect($url);
    } else if ($zipfile = pack_files($filesforzipping)) {
        send_temp_file($zipfile, 'Certificados-' . $course->shortname . '.zip');
    }
    return $result;
}

function pack_files($filesforzipping)
{
    global $CFG;
    // Create path for new zip file.
    $tempzip = tempnam($CFG->tempdir . '/', 'customcert_');
    // Zip files.
    $zipper = new zip_packer();
    if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
        return $tempzip;
    }
    return false;
}
