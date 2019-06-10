# grav-plugin-richaudeau

The `richaudeau` Plugin for [Grav CMS](http://github.com/getgrav/grav) modify text according to Richaudeau's advises to enhance the readability of a text:

- Each sentence starts with a bold character,
- An unbreakable space is added between two sentences.


## Installation

Installing the `richaudeau` plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

<!--
### GPM Installation (preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install richaudeau

This will install the `richaudeau` plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/richaudeau`.
-->

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `richaudeau`. You can find these files on [GitHub](https://github.com/drebaud/grav-plugin-richaudeau)<!-- or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras)-->.

You should now have all the plugin files under

    /your/site/grav/user/plugins/richaudeau


## Configuration

Before configuring this plugin, you should copy the `user/plugins/richaudeau/richaudeau.yaml` to `user/config/plugins/richaudeau.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
```


## Usage

**TODO** Describe how to use the plugin.


## Credits

This plugin use the great, but unfortunately dead project [_PHP Typography 1.21_](http://kingdesk.com/projects/php-typography/) from [Jeffrey D. King](http://kingdesk.com/about/jeff/).


## To Do

- [ ] Write the documentation (usage…)
