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
$ phun compress <all|gzip|brotli> <target_file>
$ phun create <module>
$ phun install <module> for <update|install>
$ phun model <module> <table> <q_field>
$ phun remove <module>
$ phun sync <module> <target_dir> <update|install>
$ phun watch <module> <target_dir> <update|install>
```

## PHP Extensi

Untuk kompresi dengan brotli, pastikan ekstensi php 
[kjdev/php-ext-brotli](https://github.com/kjdev/php-ext-brotli) sudah terpasang.