[![Latest Stable Version](https://poser.pugx.org/codeq/linkchecker/v/stable)](https://packagist.org/packages/codeq/linkchecker)
[![License](https://poser.pugx.org/codeq/linkchecker/license)](LICENSE)

# CodeQ.LinkChecker

## Finds broken and misconfigured links in your Neos project

The link checker has the following methods to find broken links:

 - Every time you open the backend module it checks the existence of all internal page links `node://XXX` in all node properties (if performant)
 - The command controller `./flow linkchecker:sync` will crawl all in the settings configured pages and check the following:
   - Do all intern links `node://XXX` point to visible pages (not hidden, not hidden via visible before or visible after)?
   - Do external links point to valid pages (http status code 2xx)

## Installation

CodeQ.LinkChecker is available via packagist run `composer require codeq/linkchecker`.
We use semantic versioning so every breaking change will increase the major-version number.

## Usage

Configure the link checker sync in your settings, like this:

```yaml
CodeQ:
  LinkChecker:
    # how many concurrent requests should the command controller perform
    # If set too high, you will DDoS your server
    concurrency: 10
  pagesToCrawl:
    - https://neos.io
    - https://codeq.at
    - https://example.org
    - https://example.org/a-nowhere-linked-page-to-also-check
```

Setup a cronjob e.g. daily to execute `./flow linkchecker:sync` 

## Limitations and possible future Features:
 - Support additional languages
 - Use a job queue for crawling
 - Update the link checks after a page is published via a job queue
 - Check external links against malware oder security adviser lists
 - Find all occurrences of external links to internal pages
 - Check against deny list (e.g. list of competitors)
 - Check for broken links in other workspaces

## FAQ

### Why don't you also check asset links?

The Neos media browser does not allow editors the deletion of assets in usage, therefore asset links can only be broken because of system errors on your virtual machine e.g. bcause of bad syncs or thumbnail creation exceptions. For this, please use `./flow resource:clean` to validate your media assets.

## License

The GNU GENERAL PUBLIC LICENSE, please see [License File](LICENSE) for more information.

## Sponsors & Contribution

The development of this plugin was kindly sponsored by [Code Q](http://codeq.at/). 

The package is based on the `Unikka/LinkChecker` package, which does a great job at finding all broken external links. This package extends the features a lot, offers a new UI and introduces new dependencies.

We will gladly accept contributions. Please send us pull requests.
