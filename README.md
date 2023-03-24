# toopt
Toopt is a command-line posting utility for mastodon written in PHP. 

Toopt is designed for ease of composability, ie. in scripts or pipelines, but also works as a utility. Toopt is not a full-featured mastodon client, and is not intended to be; it is for posting only. Toopt is written as a single file.

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
Content warnings (or spoilers) can be added to toots using the `--cw=` argument

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

### Automatic threading
Content that is longer than 500 characters will be automatically threaded. Threads will be broken on newlines or punctuation, if possible. Thread footers, ie. "1/3", will be appended to the bottom of each toot.

### Deliberate threading
A toot thread can be built by simply passing multiple toot arguments. ie.

```bash
toopt /path/to/toot1.txt /path/to/toot2.txt "this is toot3"
```

Files or string arguments can be used as toots for a thread. Toots are threaded with the left-most toot being the first, descending rightward.

**Note:** Content from `STDIN` is given priority. If content is piped into to `toopt`, all other content is ignored.

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

Display help
```bash
toopt.php --help
```
