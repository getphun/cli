# CLI

## Instalasi

```
$ git clone git@github.com:getphun/cli.git .
$ chmod +x cli.php
$ ln -s "$(pwd)/cli.php" /usr/bin/phun
$ ln -s "$(pwd)/bash/bash_autocompletion.sh" /etc/bash_completion.d/phun
```

## Penggunaan

```
$ phun -h
$ phun -v
$ phun create <module>
$ phun sync <module> <target_dir> <update|install>
$ phun watch <module> <target_dir> <update|install>
```