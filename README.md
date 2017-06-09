# URL to PDF

The URL to PDF extension allows you to generate PDF files for a whole or subset of your Symphony page.

- Version: 0.3
- Date: 9th July 2011
- Requirements: Symphony 2.3 or newer, <http://github.com/symphonycms/symphony-2/>
- Author: Brendan Abbott, brendan@bloodbone.ws & Jon Mifsud, jonathan@maze.digital
- GitHub Repository: <http://github.com/brendo/urltopdf>

## Installation

1. Upload the `urltopdf` folder to your Symphony `/extensions` folder.
2. Enable it by selecting the "URL to PDF" from the Symphony extensions page, choose "Enable/Install" from the "With Selected..." menu, then click Apply.
3. You can now add the page type of `pdf` or `pdf-attachment` to your pages

## Usage

Please refer to the [wiki](https://github.com/brendo/urltopdf/wiki) for how to use URL to PDF and for further information.

## Issues

This extension is relatively untested at the moment so there will be likely be bugs. Please report them as you find them on the [issue tracker](https://github.com/brendo/urltopdf/issues)

## Add Attachments

At the very start of your HTML PDF add the following structure

    <attachments>
    	<attachment>path/from/workspace.pdf</attachment>
    	<attachment>another/file/from/workspace.pdf</attachment>
    </attahcments>

This will be removed from your html output and appended at the end of your PDF. At this point there is no control over where the PDFs are added. However that can be looked into at a later stage.