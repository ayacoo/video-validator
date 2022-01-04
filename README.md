# TYPO3 Extension video_validator

## 1 Features

* Checks if your Youtube or Vimeo videos are still available in the TYPO3 project
* Can send you reports by email
* Can also check more media extensions through flexible extensibility

## 2 Usage

### 2.1 Installation

#### Installation using Composer

The recommended way to install the extension is using Composer.

Run the following command within your [Composer][1] based TYPO3 project:

```
composer require ayacoo/video-validator
```

Do not forget to activate the extension in the extension manager and to update the database once, so that the two new
fields are added.

### 2.2 How it works

The extension fetches all files from the sys_file that are linked to this extension based on the extension, e.g.
Youtube. Thereby a validation_date is considered.

If the list is through, a new run begins after 7 days.

Using the oEmbed API of the providers, you can read the status of a video without an API key. Private videos are marked
as faulty, but cannot be saved in TYPO3 anyway. Note the difference between private videos and unlisted videos.

### 2.3 Supported media extensions

- Youtube
- Vimeo
- Custom media extension, see doc below

### 2.4 What do I do if a video is not accessible?

It may happen that at some point videos are no longer accessible. These are listed in the report mail as invalid videos.
TYPO3 offers a number next to the file in the file list. If you click on it, all references to this file are listed. Now
you can take care of the corresponding corrections.

## 3 Administration corner

### 3.1 Versions and support

| News        | TYPO3      | PHP       | Support / Development                   |
| ----------- | ---------- | ----------|---------------------------------------- |
| 1.x         | 10.x - 11.x| 7.4 - 8.0 | features, bugfixes, security updates    |

### 3.2 Release Management

video_validator uses [**semantic versioning**][2], which means, that

* **bugfix updates** (e.g. 1.0.0 => 1.0.1) just includes small bugfixes or security relevant stuff without breaking
  changes,
* **minor updates** (e.g. 1.0.0 => 1.1.0) includes new features and smaller tasks without breaking changes,
* and **major updates** (e.g. 1.0.0 => 2.0.0) breaking changes which can be refactorings, features or bugfixes.

### 3.3 Contribution

**Pull Requests** are gladly welcome! Nevertheless please don't forget to add an issue and connect it to your pull
requests. This is very helpful to understand what kind of issue the **PR** is going to solve.

**Bugfixes**: Please describe what kind of bug your fix solve and give us feedback how to reproduce the issue. We're
going to accept only bugfixes if we can reproduce the issue.

### 3.4 Email settings

To define a sender for the email, the configuration ```$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']```
from the Install Tool is used.

Because the FluidEmail is used by TYPO3, you can of course also easily overwrite the template for the status email.

## 4 Developer corner

### 4.1 CLI calls

The calls can be retrieved directly via CLI or you can set up corresponding jobs in the scheduler. Advantage of the
scheduler: The TYPO3 is displayed correctly in the mail: https://forge.typo3.org/issues/93940

It is unclear how many fast accesses in a row the oEmbed API allows as a maximum. Therefore it is better to think small
limits.

#### videoValidator:validate

Validates a defined number of videos for the defined media extension

```
bin/typo3 videoValidator:validate extension limit
```

Example:

```
bin/typo3 videoValidator:validate Vimeo 10
```

#### videoValidator:report

Create an email report of Youtube videos from the last 7 days

```
bin/typo3 videoValidator:report days receiver extension
```

Example:

```
bin/typo3 videoValidator:report 7 receiver@example.com,receiver2@example.com Youtube
```

#### videoValidator:reset

Resets all video states of a media extension

```
bin/typo3 videoValidator:reset extension
```

Example:

```
bin/typo3 videoValidator:reset Youtube
```

### 4.2 Register your custom validator

This TYPO3 extension is built in such a way that other media extensions can also be checked. For this, the media
extension must be registered in ```$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers']``` and you must
register a validator via an event.

As example you can use the [EXT:tiktok][4].

### EventListener registration

```
services:
  Ayacoo\Tiktok\Listener\ValidatorListener:
    tags:
      - name: event.listener
        identifier: 'tiktok/crawler'
        method: 'setValidator'
        event: Ayacoo\VideoValidator\Event\ModifyValidatorEvent
```

### EventListener

```
<?php
declare(strict_types=1);

namespace Ayacoo\Tiktok\Listener;

use Ayacoo\Tiktok\Crawler\TiktokValidator;
use Ayacoo\VideoValidator\Event\ModifyValidatorEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ValidatorListener
{
    public function setValidator(ModifyValidatorEvent $event): ModifyValidatorEvent
    {
        $extension = strtolower($event->getExtension());
        if ($extension === 'tiktok') {
            $validator = GeneralUtility::makeInstance(TiktokValidator::class, $extension);
            $event->setValidator($validator);
        }
        return $event;
    }
}

```

### Custom validator

```
<?php

declare(strict_types=1);

namespace Ayacoo\Tiktok\Crawler;

use Ayacoo\Tiktok\Helper\TiktokHelper;
use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidator;
use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidatorInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TiktokValidator extends AbstractVideoValidator implements AbstractVideoValidatorInterface
{
    private TiktokHelper $tiktokHelper;

    private string $username;

    /**
     * @param string $extension
     */
    public function __construct(string $extension)
    {
        $this->tiktokHelper = GeneralUtility::makeInstance(TiktokHelper::class, $extension);
    }

    /**
     * @param string $mediaId
     * @param string $format
     * @return string
     */
    public function getOEmbedUrl(string $mediaId, string $format = 'json'): string
    {
        return sprintf(
            'https://www.tiktok.com/oembed?url=https://www.tiktok.com/video/%s',
            rawurlencode($mediaId)
        );
    }

    /**
     * @param File $file
     * @return string
     */
    public function getOnlineMediaId(File $file): string
    {
        $this->username = $file->getProperty('tiktok_username') ?? '';
        return $this->tiktokHelper->getOnlineMediaId($file);
    }

    /**
     * @param string $mediaId
     * @return string
     */
    public function buildUrl(string $mediaId): string
    {
        return '(https://www.tiktok.com/@' . $this->username . '/' . $mediaId . ')';
    }
}

```

With the custom validator you have to pay attention to the interface, so that you have a correct structure for the
checks.

## 5 Thanks / Notices

Special thanks to Georg Ringer and his [news][3] extension. A good template to build a TYPO3 extension. Here, for
example, the structure of README.md is used.


[1]: https://getcomposer.org/

[2]: https://semver.org/

[3]: https://github.com/georgringer/news

[4]: https://github.com/ayacoo/tiktok
