# Checklist

Check before merging:

- [ ] `VERSION` is updated to reflect the new version.
- [ ] `CHANGELOG.md` is updated to reflect the changes made in this merge request.

# Changelog

This release comes with the following changes:

- 

# Deployment instructions

In a terminal of choice:

```shell script
cd /path/to/donlsync
git pull
composer install --prefer-dist --no-dev --no-suggest --optimize-autoloader --classmap-authoritative
```
