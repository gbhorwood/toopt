# toopt
Toopt is a command-line posting utility for mastodon written in PHP. 

Toopt can post text content from the command line, as well as from files, piped content, or interactively. It allows for image uploads, with optional descriptions, and handles multiple accounts.

Toopt is written as a single file with no dependencies.

## Installation
The easiest installation is to download directly.

Using curl:
```bash
curl https://toopt.fruitbat.studio/toopt.php > ./toopt.php
chmod 755 ./toopt.php
```

Or wget:
```bash
wget https://toopt.fruitbat.studio/toopt.php -O ./toopt.php
chmod 755 ./toopt.php
```

## Prerequisites
Toopt is written in PHP and requires the following:
- PHP (cli) 8.1 or higher
- the PHP posix extension
- the PHP curl extension


## Usage
There are two usage activities: managing accounts, and posting toots.

## Adding and managing accounts
Before using `toopt`, you need to set up your account or accounts.

### Adding an account
To add a new account, call `toopt` with the `--add-account` argument:

```bash
toopt.php --add-account
```

Toopt will poll for login credentials.  The account will be stored and set as the default account

### List available accounts
To list all of your stored accounts, call `toopt` with `--list-accounts`:

```bash
toopt.php --list-accounts
```

The default account will be highlighted.

### Delete account
Accounts can be deleted from the store with `--delete-account=`:

```bash
toopt.php --delete-account=@name@instance.social
```

The default account cannot be delted

### Change default account
The default account can be changed with `--set-default-account`:

```bash
toopt.php --set-default-account=@name@instance.social
```

## Posting toots
The simplest usage case is to post a single toot using your default account:

```bash
toopt.php "Hello world"
```

### Using a non-default account
Tooting from an account other than the default account can be done using the `--account=` argument

```bash
toopt.php --acount=@otheraccount@mastodon.social "Hello world from other account"
```

### Adding content warnings
Content warnings (or spoilers) can be added to toots using the `--cw=` argument:

```bash
toopt.php --cw="warning! test content" "Hello world"
```

### Tooting content from files or STDIN
Toot content can be passed to `toopt` from a file or from a pipe. All of these work:

```bash
echo "Hello world from STDIN" | toopt.php
toopt.php /path/to/hello_world.txt
cat /path/to/hello_world.txt | toopt.php
```

**Note:** Content from `STDIN` is given priority. If content is piped into to `toopt`, all other content is ignored.

### Interactive composing
Content can be composed interactively using the `-i` or `--interactive` arguments:

```bash
toopt.php --interactive
```

The editor is a line editor. A confirmation prompt is shown before posting.

### Automatic threading
Content that is longer than 500 characters will be automatically threaded. Threads will be broken on newlines or punctuation, if possible. Thread footers, ie. "1/3", will be appended to the bottom of each toot.

### Deliberate threading
A toot thread can be built by simply passing multiple toot arguments. ie.

```bash
toopt.php /path/to/toot1.txt /path/to/toot2.txt "this is toot3"
```

Files or string arguments can be used as toots for a thread. Toots are threaded with the left-most toot being the first, descending rightward.

## Posting media
Media files, such as images, can be posted by providing the path to the file as an argument:

```bash
toopt.php /path/to/image.jpeg
```

Media files are identified by their file extension. The accepted extensions are:

- jpg
- jpeg
- gif
- png

Multiple media files, up to a maximum of four, can be posted:

```bash
toopt.ph /path/to/img1.png /path/to/img2.jpg /path/to/img3.jpeg /path/to/img4.gif
```

If a thread is posted, either deliberately or via automatic threading, all media files will be attached to the first toot.

### Adding media descriptions
A text description can be added to a media file using the `--description=` argument:

```bash
toopt.php --description="a nice picture" /path/to/image.jpeg
```

If multiple media are used, descriptions are applied to the media in the order they are provided. For instance:

```bash
toopt.php --description="img 1" ./img1.jpg --description="img 2" ./img2.jpg
```
or
```bash
toopt.php --description="img 1" --description="img 2" ./img1.jpg ./img2.jpg
```

### Combining media and text content
Media and text content can be combined:

```bash
toopt.php "look at these two images" /path/to/img1.jpg /path/to/img2.jpg
```
or
```bash
toopt.php /path/to/toot.txt /path/to/img1.jpg /path/to/img2.jpg
```
or
```bash
echo "look at these two images" | toopt.php /path/to/img1.jpg /path/to/img2.jpg
```

## Examples
Post a toot from the default account
```bash
toopt.php "This is a toot"
```

Post a toot as from an account other than default
```bash
toopt.php --account=@otheraccount@instance.social "This is a toot"
```

Post a toot with a content warning
```bash
toopt.php --cw="This is a content warning" "This is a toot"
```

Post a three-toot thread
```bash
toopt.php "This is toot 1" "This is toot2" "This is toot3"
```

Post a three-toot thread using a mix of files and string arguments
```bash
toopt.php "This is toot 1" /path/to/toot2.txt /path/to/toot3
```

Compose and post a toot interactively
```bash
toopt.php --interactive
```
or
```bash
toopt.php -i
```

Post a media file as a toot
```bash
toopt.php /path/to/image.jpg
```

Post several media files, up to four, as a toot
```bash
toopt.php /path/to/image.jpg /path/to/otherimage.jpg
```

Add descriptions to media files
```bash
toopt.php --description="description of image 1" /path/to/img1.jpg --description="description of image 2" /path/to/img2.jpg
```
Combine media and text
```bash
toopt.php "some text" /path/to/image.jpg
```
Display help
```bash
toopt.php --help
```
