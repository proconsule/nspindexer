# NSP Indexer
> PHP Indexer for Switch NSP (XCI NSZ XCZ) made by proconsule and jangrewe

![Preview](docs/preview.jpg)

# How To

Place all files into a directory on your webserver, then copy `config.defaults.php` to `config.php` and adjust it to your needs.

Your filenames need to contain at least a Title ID in the format `[0100XXXXXXXXYYYY]`, and Updates also need a version tag like `[v1441792]`.

Hope you enjoy it!

# Features
- List NSP, XCI, NSZ and XCZ titles in a fancy way (Base Games, DLCs and Updates)
- Check For latest Update version of game file (if any)
- Compatible with tinfoil Custom Index JSON (if called with `index.php/?tinfoil`)
- Compatible with DBI plaintext list (if called with `index.php/?DBI`)

# Thanks to
- duckbill007 for support on DBI Installer
- blawar for nsp update version look suggestion and all tinfoil cool stuff
