Instructions for maintainers
============================

- When (re)building the CLDR data, you first have to remove symbolic links.
- When pushing to the GitHub repository, you first have to create symbolic links

So, a typical process would be:

```sh

$ ./bin/punic-data-builder data:symlink expand

$ ./bin/punic-data-builder data:build --docker

$ ./bin/punic-data-builder data:symlink compact

```
