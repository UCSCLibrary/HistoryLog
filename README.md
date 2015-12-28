History Log (plugin for Omeka)
==============================

[History Log] is an [Omeka] plugin that logs creation, updates, deletion, import
and export of Omeka items, collection and files and allows administrators to
recall this information later.

This is not a replacement for your regular backups of the database, even if
each change is logged.

If you use this plugin, please take a moment to submit feedback about your experience, so we can keep making Omeka better: [User Survey] (https://docs.google.com/forms/d/1LrQ-E0gQ9Qh0CSSMa8MMfK6WxHCUoT6vqiv42QG72qo/viewform?usp=send_form "User Survey")

Installation
------------

Uncompress files and rename plugin folder "HistoryLog".

Then install it like any other Omeka plugin.


Notes
-----

- Logging is done via standard hooks. If a plugin bypasses the standard methods,
some logs may be missing.
- Some standard methods don't use hooks, for example `deleteElementTextsByElementId()`.
They should not be used internally. Omeka uses them, but fires hooks anyway.


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
[Omeka]: http://omeka.org
[plugin issues]: https://github.com/UCSCLibrary/HistoryLog/issues
[GNU/GPL v3]: https://www.gnu.org/licenses/gpl-3.0.html
[Daniel-KM]: https://github.com/Daniel-KM
