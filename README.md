History Log (plugin for Omeka)
==============================

[History Log] is an [Omeka] plugin that logs creation, updates, deletion, import
and export of Omeka items, collection and files and allows administrators to
recall this information later.

This is not a replacement for your regular backups of the database, even if each
each change is logged and each record can potentially be partially recovered at
any time, except  files. An undo button allows to recover the deletion of a
record.

The logs are used by the plugin [Curator Monitor], that computes statistics and
allows to follow selected fields with limited vocabularies, for instance a
special element "Metadata Status" with values "Incomplete", "Complete",
"Fact Checked", "Ready to Publish" and "Published".


Notes
-----

* Logging

  - If the plugin has not been installed with Omeka, older records will not have
  log entries and their stats will be partial.
  - Logging is done via standard hooks. If a plugin bypasses the standard
  methods, some logs may be missing.
  - Some standard methods don't use hooks, for example `deleteElementTextsByElementId()`.
  They should not be used internally. Omeka uses them, but fires hooks anyway.

* Recovering of a deleted record

  - Records are recreated with the same id.
  - It's recommended to undelete collections before their items.
  - Files can't be recovered, but their metadata are logged, so they can be
  manually recreated as long as files are backuped.
  - Non standard metadata are not saved and can't be recreated: status public or
  featured, item type, tags, flag "html" of element texts, etc. For the owner,
  the user who deleted the record is used.
  - Check Omeka logs after a successful rebuild to see possible issues.

* Export of logs

  Logs can be filtered and exported via the main page of the plugin. Supported
  formats are:

  - CSV, with values separated by a tabulation.
  - [OpenDocument Spreadsheet] or "ods", the normalized format for
  spreadsheets, that  can be open by any free spreadsheets like [LibreOffice],
  or not free ones. This format requires that Zip to be installed on the server
  (generally by default).
  - [Flat OpenDocument Spreadsheet] or "fods", another standard format that can
  be opened by any free spreadsheets or by any text editor (this is a simple xml
  file). Note: With old releases of [LibreOffice] for Windows, a little free
  [filter] may need to be installed.


Installation
------------

Uncompress files and rename plugin folder "HistoryLog".

Then install it like any other Omeka plugin.


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and database regularly so you can
roll back if needed.


Troubleshooting
---------------

See online issues on the [plugin issues] page on GitHub.


License
-------

This plugin is published under [GNU/GPL v3].

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.


Copyright
---------

* Copyright 2014-2015 UCSC Library Digital Initiatives
* Copyright Daniel Berthereau, 2015 (see [Daniel-KM] on GitHub)


[History Log]: https://github.com/UCSCLibrary/HistoryLog
[Omeka]: https://omeka.org
[Curator Monitor]: https://github.com/Daniel-KM/CuratorMonitor
[OpenDocument Spreadsheet]: http://opendocumentformat.org/
[LibreOffice]: https://www.libreoffice.org/
[Flat OpenDocument Spreadsheet]: https://en.wikipedia.org/wiki/OpenDocument_technical_specification
[filter]: http://www.sylphide-consulting.com/shapekit/spreadsheet-generation/15-opendocument-flat-format
[plugin issues]: https://github.com/UCSCLibrary/HistoryLog/issues
[GNU/GPL v3]: https://www.gnu.org/licenses/gpl-3.0.html
[Daniel-KM]: https://github.com/Daniel-KM
