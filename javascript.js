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
 * Version information
 *
 * @package    mod
 * @subpackage groupmanagement
 * @copyright  2013 Université de Lausanne
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


var NDY = YUI().use("node", function(Y) {
    var groupmanagement_memberdisplay_click = function(e) {

        var names = Y.all('div.groupmanagements-membersnames'),
            btnShowHide = Y.all('a.groupmanagement-memberdisplay');

        btnShowHide.toggleClass('hidden');
        names.toggleClass('hidden');

        e.preventDefault();

    };
    Y.on("click", groupmanagement_memberdisplay_click, "a.groupmanagement-memberdisplay");

    var groupmanagement_descriptiondisplay_click = function(e) {

        var names = Y.all('div.groupmanagements-descriptions'),
            btnShowHide = Y.all('a.groupmanagement-descriptiondisplay');

        btnShowHide.toggleClass('hidden');
        names.toggleClass('hidden');

        e.preventDefault();

    };
    Y.on("click", groupmanagement_descriptiondisplay_click, "a.groupmanagement-descriptiondisplay");
    Y.delegate('click', function() { Y.one("table.groupmanagements~input[type='submit'][class='button']").hide(); },  Y.config.doc, "table.groupmanagements input[id^='choiceid_'][type='radio'][checked]", this);
    Y.delegate('click', function() { Y.one("table.groupmanagements~input[type='submit'][class='button']").show(); },  Y.config.doc, "table.groupmanagements input[id^='choiceid_'][type='radio']:not([checked])", this);
});
