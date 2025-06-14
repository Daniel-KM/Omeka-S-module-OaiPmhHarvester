OAI-PMH Harvester (plugin for Omeka)
====================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[OAI-PMH Harvester] is a module for [Omeka S] that allows to import records from
OAI-PMH data providers.

Some online repositories expose their metadata through the [Open Archives Initiative Protocol for Metadata Harvesting]
(OAI-PMH). This module makes it possible to harvest that metadata, mapping it to
the Omeka data model. The module can be used for one-time data transfers, or to
keep up-to-date with changes to an online repository (not yet implemented).

Currently the module is able to import [Dublin Core], and [METS] if the profile
uses Dublin Core.

* Dublin Core is an internationally recognized standard for describing any
  resource. Every OAI-PMH data provider should implement this standard.
* METS is the Metadata Encoding and Transmission Standard and is mainly designed
  for digitalized items, as books, journals, manuscripts, video and audio.


Installation
------------

Uncompress files and rename module folder `OaiPmhHarvester`.

See general end user documentation for [installing a module] and follow the
config instructions.


Usage
-----

### Performing a harvest

* Go to the Oai-Pmh Harvester page.
* Type or paste the address of the repository you want to fetch data from
  (usually something like *http://sample-host.org/data/oai*)
* Cilck the **View Sets** button.
* Choose the sets you want to harvest by checking the box **Harvest this set ?**
  for each set you want to be harvested.
* Choose the protocol you want to use : **oai_dc** or **oai_dcterms** or
  **mets** for each set.
* Click the **Harvest** button.
* A task should be created. The module creates a collection for each fetched set
  and then proceeds to harvest the content.


TODO
----

- [ ] Add a search field in the advanced search.
- [ ] Check harvest of a repository without sets.
- [ ] Original plugins shows details with duplicates of harvested entities.
- [ ] Allow to update and check deletion.
- [ ] Integrate more formats ([CDWA Lite], Ead, etc.).


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under [GNU/GPL] v3.

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

* Copyright 2008-2013 Roy Rosenzweig Center for History and New Media
* Copyright Vincent Buard, 2017 (see [Numerizen])
* Copyright Daniel Berthereau, 2014-2025 (see [Daniel-KM])

This module has first been built by [Numerizen] as a simplified version of the
[plugin] for [Omeka Classic], for PSL University. Then, it was improved for the
[Bibliothèque nationale universiaire de Strasbourg] for the digital library
[Numistral] and for various libraries.


[OAI-PMH Harvester]: https://gitlab.com/Daniel-KM/Omeka-S-module-OaiPmhHarvester
[Omeka S]: https://omeka.org/s
[Open Archives Initiative Protocol for Metadata Harvesting]: http://www.openarchives.org/pmh
[Dublin Core]: http://dublincore.org/documents/dces
[CDWA Lite]: http://www.getty.edu/research/conducting_research/standards/cdwa/cdwalite.html
[METS]: http://www.loc.gov/standards/mets
[MARCXML]: http://www.loc.gov/standards/marcxml
[RFC 1807]: http://www.ietf.org/rfc/rfc1807.txt
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-OaiPmhHarvester/-/issues
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[Omeka Classic]: https://omeka.org/classic
[plugin]: https://github.com/omeka/plugin-OaipmhHarvester
[Bibliothèque nationale universiaire de Strasbourg]: https://www.bnu.fr
[Numistral]: https://omeka.numistral.fr
[Numerizen]: http://omeka.numerizen.com
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
