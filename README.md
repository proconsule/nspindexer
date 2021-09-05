# NSP Indexer
> PHP Indexer for Switch NSP (XCI NSZ XCZ) by proconsule and jangrewe

![Preview](docs/preview.jpg)

# How To
Place all files into a directory on your webserver, then copy `config.defaults.php` to `config.php` and adjust it to your needs.

Your filenames need to contain at least a Title ID in the format `[0100XXXXXXXXYYYY]`, and Updates also need a version tag like `[v1441792]`.

For advanced features a 64 bit OS is needed!

Hope you enjoy it!

# Features
- List NSP, XCI, NSZ and XCZ titles in a fancy way (Base Games, DLCs and Updates)
- Check For latest Update version of game file (if any)
- Compatible with tinfoil Custom Index JSON (if called with `index.php/?tinfoil`)
- Compatible with DBI plaintext list (if called with `index.php/?DBI`)
- Net Install (if TCP port 2000 of Switch is reachable by webserver)
- NSP Internal TitleID Check
- XCI Internal TitleID Check (if keys supplied)
- NSP & XCI File Decryption
- NCA Header Signature Check
- Download of individual internal file (NCA TIK XML CERT)
- Download of Switch FW Update from XCI file (as a single tar file)
- File Rename Based on TitleID & Version

# Known Issue
- 32Bit System suffer for >2GB limit in many way (fseek and so on) so some features are not working like Rom Info. for Windows users use php > 7.0 as also on 64bit machines lower versions have 32bit integers.

# FAQ
**Question:** I am a 32bit system and rom info button doesn't show, why?

**Answer** Rom info button is disabled on 32bit system. sorry but with php on a 32bit system is impossible to do decryption

**Q:** What is the differnces between master and dev branch?

**A:** Master branch is stable and updated only when all features are tested and stable. Dev branch often have more features but may (mostly with proconsule commits) have bugs.

**Q:** I found a bug, where i can report that?

**A:** Here on github as usual, or on GBAtemp forum here https://gbatemp.net/threads/nsp-indexer.591541/

# Thanks to
- SciresM for aes128.py we ported to PHP for NCA decryption
- duckbill007 for support on DBI Installer
- blawar for nsp update version look suggestion and all tinfoil cool stuff
- Ejec at GBAtemp forum (for his bugs reports)
