mkdam2fal
=======

What does it do?
----------------

With the extension MKDAM2FAL 6.2 you are able to import DAM data into the structure of FAL. The import includes the following information:

-   Transfer or update of the relations between the files and the content elements from DAM to FAL
-   Transfer of the meta information of the files and relations
-   Transfer of the DAM categories and their relations
-   Replacement of the DAM media-tag with the FAL link-tag
-   Update of the Thumbnail information

With this functionality the extension MKDAM2FAL 6.2 supports you by updating from TYPO3 4.x with DAM to 6.2.x with FAL.

As this is a fork of the we\_dam2fal extension we provide some additional features.

-   it's not only possible to migrate files which reside in the fileadmin folder but in every possible storage
-   in the extension configuration it can be configured how many datasets are migrated per submit. With we\_dam2fal the limit are 10000 datasets per submit
-   the logs are no more overwritten
-   the backend module works also with HTTPS connections
-   better and more secure navigation for the logs
-   added composer support
-   make usage of the new TYPO3 data model for categories


[User](Documentation/User/Index.md)

[Administrator](Documentation/Administrator/Index.md)

[Configuration](Documentation/Configuration/Index.md)

[KnownProblems](Documentation/KnownProblems/Index.md)

[ChangeLog](Documentation/ChangeLog/Index.md)