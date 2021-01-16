GotifyDue Plugin for Kanboard
=============================

This Automatic Action will allow you to send [Gotify](https://gotify.net/) notifications of impending due date for tasks.

Developed using [Kanboard](https://kanboard.org) Version 1.2.18

Author
------

- Benedikt Hopmann
- License MIT
- Based upon [SendEmailCreator](https://github.com/creecros/SendEmailCreator) by Craig Crosby

Requirements
------------

- Kanboard >= 1.0.37
- [Gotify Plugin for Kanboard](https://github.com/bhopmann/kanboard-plugin-gotify)
- Existing [Gotify](https://github.com/gotify/server) server

Installation
------------

You have the choice between two methods:

1. Download the zip file and decompress everything under the directory `plugins/GotifyDue`
2. Clone this repository into the folder `plugins/GotifyDue`

Note: Plugin folder is case-sensitive.

Usage
-----

This plugin will add one new automatic actions to Kanboard: *Send Gotify notification of impending task due date*

This action will send a gotify notification of an impending due date to either the task creator, assignee or both. Duration (in hours) is defined by user, i.e. 1 hour would start sending notifications of tasks when there is less than 1 hour before due date.

This plugins depends on configuring a cronjob, e.g. `cli projects:daily-stats` running every 15 minutes.

Notifications aren't send if the task is overdue, cause this is handled via `notification:overdue-tasks`.


Troubleshooting
---------------

- Enable the PHP debug mode
- All errors are recorded in the logs
- Enable verbose mode in file `plugins/GotifyDue/Action/TaskGotifyDue.php` (`$verbose = true;` and `$gotify_verbose = true;`)
