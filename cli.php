#!/bin/env php
<?php

class Phun
{
    static $version = '0.0.2';
    
    static $mod_files = [];
    static $update_db = null;
    
    static $routes = [
        [ ['-h', '--help'],     'appHelp',      'Show this help text' ],
        [ ['-v', '--version'],  'appVersion',   'Show version number' ],
        [ ['compress'],         'modCompress',  'Compress target files with custom compression', 'phun compress <gzip|brotli|all> <files>' ],
        [ ['create'],           'modCreate',    'Create new blank module', 'phun create <module>' ],
//         [ ['init'],             'modInit',      'Create new project on current directory' ],
        [ ['install'],          'modInstall',   'Install new module here', 'phun install <module> [for <update|install>] [from <git-url>]' ],
        [ ['model'],            'modModel',     'Create new blank model under some module', 'phun model <module> <table> <q_field>' ],
        [ ['remove'],           'modRemove',    'Remove exists module', 'phun remove <module>' ],
        [ ['sync'],             'modSync',      'Sync some module to any other project', 'phun sync <module> <target> <rule>' ],
//         [ ['update'],           'modUpdate',    'Update exists module', 'phun update <module>' ],
        [ ['watch'],            'modWatch',     'Watch module files change and sync with any other project', 'phun watch <module> <target> <rule>' ]
    ];
    
    static function appHelp(){
        Phun::echo('Usage: phun [options...]');
        Phun::echo('Options:');
        
        foreach(self::$routes as $route){
            $tx = ' ';
            foreach($route[0] as $cm)
                $tx.= $cm . ', ';
            $tx = chop($tx, ', ');
            $tx.= str_repeat(' ', (17-strlen($tx)));
            $tx.= $route[2];
            
            if(isset($route[3])){
                $tx.= PHP_EOL;
                $tx.= str_repeat(' ', 5);
                $tx.= '$ ' . $route[3];
            }
            Phun::echo($tx);
        }
    }
    
    static function appVersion(){
        Phun::echo('PhunCLI v' . self::$version);
    }
    
    static function arrayToConfigText(Array $arr){
        $nl = PHP_EOL;
        $tx = '<?php' . $nl;
        $tx.= '/**' . $nl;
        $tx.= ' * ' . $arr['__name'] . ' config file' . $nl;
        $tx.= ' * @package ' . $arr['__name'] . $nl;
        $tx.= ' * @version ' . $arr['__version'] . $nl;
        $tx.= ' * @upgrade true' . $nl;
        $tx.= ' */' . $nl;
        $tx.= $nl;
        $tx.= 'return [';
        if(count($arr)){
            $tx.= $nl;
            $tx.= self::arrayToSource($arr);
        }
        $tx.= '];';
        return $tx;
    }
    
    static function arrayToSource($arr, $level=1){
        $nl = PHP_EOL;
        $tx = '';
        $spaces = str_repeat(' ', ($level*4));
        
        $indexed_array = self::isIndexedArray($arr);
        foreach($arr as $ind => $value){
            // re-add backslash
            if(strstr($ind,'\\'))
                $ind = str_replace('\\', '\\\\', $ind);
            
            $tx.= $spaces;
            if(!$indexed_array){
                if(is_integer($ind)){
                    $tx.= "$ind => ";
                }else{
                    $tx.= "'$ind' => ";
                }
            }
            if(is_array($value)){
                $tx.= '[';
                if(count($value)){
                    $tx.= $nl;
                    $tx.= self::arrayToSource($value, ($level+1));
                }
            }elseif(is_integer($value)){
                $tx.= "$value," . $nl;
            }elseif(is_string($value)){
                if(strstr($value,'\\'))
                    $value = str_replace('\\', '\\\\', $value);
                $tx.= "'$value'," . $nl;
            }elseif(is_bool($value)){
                $tx.= ($value?'TRUE':'FALSE') . ',' . $nl;
            }
            if(!$indexed_array){
                if(is_array($value)){
                    if(count($value))
                        $tx.= $spaces;
                    $tx.= '],' . $nl;
                }
            }
        }
        return rtrim($tx,', '.$nl) . $nl;
    }
    
    static function cliError(){
        global $argv;
        Phun::close(sprintf('Please hit `%s --help` for more information about the command usage.', $argv[0]));
    }
    
    static function cloneModule($git){
        $exec_result = exec('which git 2>&1', $out, $ret);
        if($ret)
            return self::close('Git is not installed. Please install it to continue installation.');
        
        $temp = tempnam(sys_get_temp_dir(), 'phun-');
        unlink($temp);
        
        $cmd = "git clone $git $temp 2>&1";
        
        self::echo('Cloning the project from ' . $git . ' ...');
        $exec_result = exec($cmd, $out, $ret);
        
        if($ret)
            return self::close('Unable to clone the project. Please make sure you have the correct access rights and the repository exists.');
        return $temp;
    }
    
    static function close($msg){
        Phun::echo($msg);
        exit;
    }
    
    static function deb(){
        $args = func_get_args();
        foreach($args as $arg){
            if(is_null($arg))
                echo 'NULL';
            elseif(is_bool($arg))
                echo $arg ? 'TRUE' : 'FALSE';
            elseif(is_string($arg))
                echo $arg;
            else
                print_r($arg);
            echo PHP_EOL;
        }
        exit;
    }
    
    static function echo($str){
        echo $str . PHP_EOL;
    }
    
    static function init(){
        global $argv;
        
        $action = 'cliError';
        $args   = [];
        foreach($argv as $i => $arg){
            if($i === 0)
                continue;
            
            if($i === 1)
                $action = $arg;
            else
                $args[] = $arg;
        }
        
        foreach(self::$routes as $route){
            if(!in_array($action, $route[0]))
                continue;
            $act = $route[1];
            self::$act($args);
            exit;
        }
        
        self::cliError();
    }
    
    static function installModule($module, $for, $from){
        $here = getcwd();
        $temp = self::cloneModule($from);
        
        $config = $temp . '/modules/' . $module . '/config.php';
        if(!is_file($config))
            self::close("Module named \033[1m{$module}\033[0m is broken.");
        
        self::echo("Installing module \033[1m{$module}\033[0m ...");
        $config = require $config;
        $files  = $config['__files'];
        self::$mod_files = self::scanModuleFiles($files, $temp);
        self::syncModuleFiles(self::$mod_files, $temp, $here, 'install');
        $exec_ret = exec('rm -Rf ' . $temp, $out, $ret);
        
        $devs = $config['__dependencies'] ?? null;
        if($devs){
            foreach($devs as $mod){
                $required = !strstr($mod, '/');
                $mods = explode('/', $mod);
                foreach($mods as $mo){
                    $mo  = trim($mo);
                    if(!$mo)
                        continue;
                    $git = 'git@github.com:getphun/' . $mo . '.git';
                    if(is_dir($here . '/modules/' . $mo))
                        continue;
                    
                    $conf = true;
                    if(!$required){
                        $ask_conf = readline("Module \033[1m{$mo}\033[0m is optional, would you like me to install it? ([Y]es/no): ");
                        $ask_conf = strtolower($ask_conf);
                        if($ask_conf == 'no' || $ask_conf == 'n')
                            $conf = false;
                    }
                    
                    if($conf)
                        self::installModule($mo, $for, $git);
                }
            }
        }
        
        // update database if exists
        $db_dir = $here . '/modules/' . $module . '/_db';
        if(!is_dir($db_dir))
            return;
        
        $global_db_dir = $here . '/.db';
        if(!is_dir($global_db_dir))
            mkdir($global_db_dir);
        
        $module_db = file_get_contents($db_dir . '/install.sql');
        
        $db_install = $global_db_dir . '/install.sql';
        $f = fopen($db_install, 'a');
        fwrite($f, "-- $module $config[__version]");
        fwrite($f, "\n");
        fwrite($f, $module_db);
        fwrite($f, "\n\n");
        fclose($f);
        
        if($for != 'update')
            return;
        
        $db_update = $global_db_dir . '/update.sql';
        if(is_file($db_update) && is_null(self::$update_db)){
            self::$update_db = true;
            
            $target_file = readline('File update.sql already exists. Would you like me to update it? Select [no] to overwrite it ([Y]es/no): ');
            $target_file = strtolower($target_file);
            if( $target_file == 'no' || $target_file == 'n' )
                unlink($db_update);
        }
        
        $f = fopen($db_update, 'a');
        fwrite($f, "-- $module $config[__version]");
        fwrite($f, "\n");
        fwrite($f, $module_db);
        fwrite($f, "\n\n");
        fclose($f);
    }
    
    static function isIndexedArray($array){
        $array_count = count($array);
        for($i=0; $i<$array_count; $i++){
            if(!array_key_exists($i, $array))
                return false;
        }
        return true;
    }
    
    static function makeConfigFile($config, $base){
        $f = fopen($base . '/config.php', 'w');
        $ctext = self::arrayToConfigText($config);
        fwrite($f, $ctext);
        fclose($f);
    }

    static function modCompress($args){
        $here = getcwd();
        $ctype = array_shift($args);
        $accetables_types = ['all', 'brotli', 'gzip'];

        if(!in_array($ctype, $accetables_types))
            Phun::close('Compression type is not supported');

        foreach($args as $file){
            $file_abs = realpath($here . '/' . $file);

            Phun::echo('Compressing file ' . $file);

            if($ctype == 'all' || $ctype == 'brotli'){
                if(!self::modCompressBrotli($file_abs))
                    Phun::echo('    Failed on brotli compression');
                else
                	Phun::echo('    Brotli compression success');
            }

            if($ctype == 'all' || $ctype == 'gzip'){
                if(!self::modCompressGZip($file_abs))
                    Phun::echo('    Failed on gzip compression');
                else
                	Phun::echo('    GZip compression success');
            }
        }

        Phun::echo('All file compression already done');
    }

    static function modCompressBrotli($file){
        $target = $file . '.br';
        $mode   = BROTLI_GENERIC;

        if(preg_match('!\.woff2$!', $file))
            $mode = BROTLI_FONT;
        else{
            $mime   = mime_content_type($file);
            if(strstr($mime, 'text'))
                $mode = BROTLI_TEXT;
        }
        
        $content = file_get_contents($file);
        $compressed = brotli_compress($content, 11, $mode);
        if(!$compressed)
            return false;

        $f = fopen($target, 'w');
        fwrite($f, $compressed);
        fclose($f);

        return true;
    }

    static function modCompressGZip($file){
        $target = $file . '.gz';
        $mode   = 'wb9';
        $error  = false;

        if(false === ($fz = gzopen($target, $mode)))
            return false;

        if(false === ($f = fopen($file, 'rb')))
            return false;

        while(!feof($f))
            gzwrite($fz, fread($f, 1024*512));
        fclose($f);
        gzclose($fz);

        return true;
    }
    
    static function modCreate($args){
        $config = [];
        
        $here = getcwd();
        
        // __name //////////////////////////////////////////////////////////////
        if(isset($args[0]))
            $config['__name'] = $args[0];
        else
            $config['__name'] = readline('Name: ');
        if(!$config['__name'])
            Phun::close('Module name is required');
        
        if(!preg_match('!^[a-z0-9-]+$!', $config['__name']))
            Phun::close('Module name is not valid');
        $name = $config['__name'];
        
        $base = 'modules/' . $name;
        $project_dir = $here . '/' . $name;
        $module_dir  = $project_dir . '/' . $base;
        
        $module = self::toNameSpace($name);
        
        // __version ///////////////////////////////////////////////////////////
        $ver = '0.0.1';
        $config['__version'] = readline('Version (' . $ver . '): ');
        if(!$config['__version'])
            $config['__version'] = $ver;
        
        // __git ///////////////////////////////////////////////////////////////
        $git = 'https://github.com/getphun/' . $name;
        $config['__git'] = readline('Git (' . $git . '): ');
        if(!$config['__git'])
            $config['__git'] = $git;
        
        // __files /////////////////////////////////////////////////////////////
        $config['__files'] = [
            'modules/' . $name => ['install', 'remove', 'update']
        ];
        
        // __dependencies //////////////////////////////////////////////////////
        $config['__dependencies'] = [];
        while($dev=readline('Dependency: '))
            $config['__dependencies'][] = $dev;
        
        $config['_services'] = [];
        $config['_autoload'] = [
            'classes' => [],
            'files'   => []
        ];
        
        // _services ///////////////////////////////////////////////////////////
        while($ser=readline('Service: ')){
            if(!preg_match('!^[a-zA-Z]+$!', $ser)){
                Phun::echo('Invalid service name, please use only (a-z)');
                continue;
            }
            $dcls = ucfirst($ser);
            $cls = readline('  Class ('.$dcls.'): ');
            if(!$cls)
                $cls = $dcls;
            
            $cls_ns = $module . '\\Service\\' . $cls;
            $config['_services'][$ser] = $cls_ns;
            $config['_autoload']['classes'][$cls_ns] = $base . '/service/' . $cls . '.php';
        }
        
        // prepare dirs
        self::r_mkdir($module_dir);
        // make config file
        self::makeConfigFile($config, $module_dir);
        // make service 
        if($config['_services']){
            $nl = PHP_EOL;
            foreach($config['_services'] as $ser => $cls){
                $cls_file = $config['_autoload']['classes'][$cls];
                $abs_cls_file = $project_dir . '/' . $cls_file;
                $abs_service_dir = dirname($abs_cls_file);
                self::r_mkdir($abs_service_dir);
                
                $clses = explode('\\', $cls);
                $cls_name = array_pop($clses);
                $cls_ns   = implode('\\', $clses);
                
                $tx = '<?php' . $nl;
                $tx.= '/**' . $nl;
                $tx.= ' * ' . $ser . ' service' . $nl;
                $tx.= ' * @package ' . $config['__name'] . $nl;
                $tx.= ' * @version ' . $config['__version'] . $nl;
                $tx.= ' * @upgrade true' . $nl;
                $tx.= ' */' . $nl;
                $tx.= $nl;
                $tx.= 'namespace ' . $cls_ns . ';' . $nl;
                $tx.= $nl;
                $tx.= 'class ' . $cls_name . ' {'. $nl;
                $tx.= '}';
                
                $f = fopen($abs_cls_file, 'w');
                fwrite($f, $tx);
                fclose($f);
            }
        }
        // make readme file
        $f = fopen($project_dir . '/README.md', 'w');
        fwrite($f, '# ' . $config['__name']);
        fclose($f);
        
        self::echo("Module \033[1m{$module}\033[0m already created.");
        exit;
    }
    
    static function modInstall($args){
        $here   = getcwd();
        $module = $args[0] ?? null;
        $ab1k   = $args[1] ?? null;
        $ab1v   = $args[2] ?? null;
        $ab2k   = $args[3] ?? null;
        $ab2v   = $args[4] ?? null;
        
        if(!$module)
            return self::cliError();
        
        $module_dir = $here . '/modules/' . $module;
        if(is_dir($module_dir))
            return self::close("Module named \033[1m{$module}\033[0m already exists");
        
        $for    = 'install';
        $from   = null;
        
        if($ab1k == 'for')
            $for = $ab1v;
        if($ab2k == 'for')
            $for = $ab2v;
        if(!in_array($for, ['install', 'update']))
            return self::cliError();
        
        if($ab1k == 'from')
            $from = $ab1v;
        if($ab2k == 'from')
            $from = $ab2v;
        
        if(is_null($from))
            $from = 'git@github.com:getphun/' . $module . '.git';
        
        if(!preg_match('!^git@([a-z0-9-.]+):([^\/]+)\/([^.]+)\.git$!', $from))
            return self::close("Module git repository URL is not valid.");
        
        self::installModule($module, $for, $from);
        self::echo("Module \033[1m{$module}\033[0m and all it's dependencies installed.");
    }
    
    static function modModel($args){
        $module = $args[0] ?? null;
        $table  = $args[1] ?? null;
        $qfield = $args[2] ?? null;
        
        $here = getcwd();
        
        if(!$module || !$table)
            return self::cliError();
        
        $module_dir = $here . '/modules/' . $module;
        if(!is_dir($module_dir))
            return self::close("Module named \033[1m{$module}\033[0m not found");
        
        $module_config_file = $module_dir . '/config.php';
        if(!is_file($module_config_file))
            return self::close("Module config file not found");
        
        $module_config = include $module_config_file;
        
        $module_ns = self::toNameSpace($module);
        
        $model_name = self::toNameSpace($table);
        $model_dir  = $module_dir . '/model';
        $model_file = $model_dir . '/' . $model_name . '.php';
        if(!is_dir($model_dir))
            self::r_mkdir($model_dir);
        
        // Model File
        $nl = PHP_EOL;
        $tx = '<?php' . $nl;
        $tx.= '/**' . $nl;
        $tx.= ' * ' . $table . ' model' . $nl;
        $tx.= ' * @package ' . $module . $nl;
        $tx.= ' * @version ' . $module_config['__version'] . $nl;
        $tx.= ' * @upgrade true' . $nl;
        $tx.= ' */' . $nl;
        $tx.= $nl;
        $tx.= 'namespace ' . $module_ns . '\\Model;' . $nl;
        $tx.= $nl;
        $tx.= 'class ' . $model_name . ' extends \\Model' . $nl;
        $tx.= '{' . $nl;
        $tx.= '    public $table = \'' . $table . '\';' . $nl;
        if($qfield)
        $tx.= '    public $q_field = \'' . $qfield . '\';' . $nl;
        $tx.= '}';
        
        $f = fopen($model_file, 'w');
        fwrite($f, $tx);
        fclose($f);
        
        // Model Autoload 
        $model_au_name = $module_ns . '\\Model\\' . $model_name;
        $model_au_file = 'modules/' . $module . '/model/' . $model_name . '.php';
        $module_config['_autoload']['classes'][$model_au_name] = $model_au_file;
        
        self::makeConfigFile($module_config, $module_dir);
        
        self::echo("Model for table \033[1m{$table}\033[0m already created.");
    }
    
    static function modRemove($args){
        $module = $args[0] ?? null;
        $here   = getcwd();
        
        if(!$module)
            return self::cliError();
        
        $module_dir = $here . '/modules/' . $module;
        if(!is_dir($module_dir))
            return self::close("Module named \033[1m{$module}\033[0m not found");
        
        $config = require $module_dir . '/config.php';
        $files  = $config['__files'];
        self::$mod_files = self::scanModuleFiles($files, $here);
        
        // remove.sql?
        $remove_sql = $module_dir . '/_db/remove.sql';
        if(is_file($remove_sql)){
            $conf = readline('There\'s remove.sql file on the module. Would you like to put it to update.sql file? ([Y]es/no): ');
            $conf = strtolower($conf);
            
            if(!$conf || $conf == 'y' || $conf == 'yes'){
                $db_update = $here . '/.db/update.sql';
                if(is_file($db_update)){
                    $target_file = readline('File update.sql already exists. Would you like me to update it? Select [no] to overwrite it ([Y]es/no): ');
                    $target_file = strtolower($target_file);
                    if( $target_file == 'no' || $target_file == 'n' )
                        unlink($db_update);
                }
                $f = fopen($db_update, 'a');
                fwrite($f, "-- $module $config[__version]");
                fwrite($f, "\n");
                fwrite($f, file_get_contents($remove_sql));
                fwrite($f, "\n\n");
                fclose($f);
            }
        }
        
        foreach(self::$mod_files as $file => $cond){
            if(!in_array('remove', $cond['rules']))
                continue;
            
            $abs = $here . '/' . $file;
            if(is_dir($abs))
                $exec_ret = exec('rm -Rf ' . $abs, $out, $ret);
            elseif(is_file($abs))
                unlink($abs);
        }
        
        self::echo("Module \033[1m{$module}\033[0m already removed.");
    }
    
    static function modSync($args){
        $here = getcwd();
        $module = $args[0] ?? null;
        $target = $args[1] ?? null;
        $rule   = $args[2] ?? 'update';
        
        $multiple = false;
        if(!$module || !$target || !in_array($rule, ['install', 'update']))
            return self::cliError();
        
        if(substr($target,-1) == '.')
            $multiple = true;
        $target = realpath($here . '/' . $target);
        
        if(!$multiple)
            $targets = [$target=>'force'];
        else{
            $targets = [];
            $dirs = array_diff(scandir($target), ['.','..']);
            if(!$dirs)
                return self::close("Target dir is empty");
            
            foreach($dirs as $dir){
                if($dir == '_source')
                    continue;
                $tgr = $target.'/'.$dir.'/site';
                if(!is_dir($tgr))
                    continue;
                $tgrm = $tgr.'/modules';
                if(!is_dir($tgrm))
                    continue;
                $tgrm = $tgrm.'/'.$module;
                if(!is_dir($tgrm)){
                    $targets[$tgr] = 'not installed';
                }else{
                    $targets[$tgr] = 'ask';
                }
            }
        }
        
        $module_base = 'modules/' . $module;
        $module_dir  = $here . '/' . $module_base;
        
        if(!is_dir($module_dir))
            return self::close("Module named \033[1m{$module}\033[0m not found");
        
        $config = require $module_dir . '/config.php';
        $files  = $config['__files'];
        self::$mod_files = self::scanModuleFiles($files, $here);
        
        foreach($targets as $target => $cond){
            if($cond === 'not installed' || $cond === 'ask'){
                $conf = true;
                $exists = $cond === 'ask' ? 'exists' : 'not installed';
                $ask_conf = readline("Sync to \033[1m{$target}\033[0m? ($exists) ([Y]es/no): ");
                $ask_conf = strtolower($ask_conf);
                if($ask_conf == 'no' || $ask_conf == 'n')
                    $conf = false;
                if(!$conf)
                    continue;
            }
            self::syncModuleFiles(self::$mod_files, $here, $target, $rule);
            self::echo("Module \033[1m{$module}\033[0m synced to \033[1m{$target}\033[0m.");
            self::echo("");
        }
        
        exit;
    }
    
    static function modWatch($args){
        $here = getcwd();
        $module = $args[0] ?? null;
        $target = $args[1] ?? null;
        $rule   = $args[2] ?? 'update';
        
        if(!$module || !$target || !in_array($rule, ['install', 'update']))
            return self::cliError();
        
        $target = realpath($here . '/' . $target);
        
        $module_base = 'modules/' . $module;
        $module_dir  = $here . '/' . $module_base;
        
        if(!is_dir($module_dir))
            return self::close("Module named \033[1m{$module}\033[0m not found");
        
        
        self::echo("Watching module \033[1m{$module}\033[0m for changes");
        
        $first_time = true;
        while(true){
            $config = require $module_dir . '/config.php';
            $files  = self::scanModuleFiles($config['__files'], $here);
            
            if(!$first_time){
                self::syncModuleFiles($files, $here, $target, $rule);
                self::$mod_files = $files;
            }else{
                $first_time = false;
                self::$mod_files = $files;
            }
            
            sleep(1);
        }
    }
    
    static function r_mkdir($dir){
        $dirs = explode('/', $dir);
        $path = '';
        foreach($dirs as $dir){
            $path.= '/' . $dir;
            if(!is_dir($path))
                mkdir($path);
        }
    }
    
    static function r_rmdir($dir){
        if(!is_dir($dir))
            return;
        
        $dir_files = scandir($dir);
        if($dir_files)
            return;
        
        rmdir($dir);
        $dir = dirname($dir);
        return self::r_rmdir($dir);
    }
    
    static function scanModuleFiles($files, $base){
        $result = [];
        
        foreach($files as $file => $rules){
            $file_abs = $base . '/' . $file;
            if(!file_exists($file_abs))
                continue;
            
            $result[$file] = [
                'stamp' => filemtime($file_abs),
                'rules' => $rules
            ];
            
            if(is_dir($file_abs)){
                $subfiles = array_diff(scandir($file_abs), ['.', '..']);
                if($subfiles){
                    $next_files = [];
                    foreach($subfiles as $subfile){
                        if(strstr($subfile, 'kate-swp'))
                            continue;
                        $next_files[$file . '/' . $subfile] = $rules;
                    }
                    $next_files = self::scanModuleFiles($next_files, $base);
                    $result = array_merge($result, $next_files);
                }
            }
        }
        
        return $result;
    }
    
    static function syncModuleFiles($files, $source, $target, $rule){
        // sync from the old one to the new one
        foreach(self::$mod_files as $file => $args){
            if(!in_array($rule, $args['rules']))
                continue;
            
            $target_abs = $target . '/' . $file;
            $source_abs = $source . '/' . $file;
            
            
            // file removed
            if(!isset($files[$file])){
                self::echo(" - File/Folder \033[1m{$file}\033[0m removed.");
                if(file_exists($target_abs)){
                    if(is_file($target_abs)){
                        unlink($target_abs);
                        $target_abs = dirname($target_abs);
                    }
                    self::r_rmdir($target_abs);
                }
                continue;
            }
            
            $type = is_file($source_abs) ? 'File' : 'Folder';
            
            // check if the file updated
            $new_args   = $files[$file];
            if($new_args['stamp'] != $args['stamp'])
                self::echo(" - $type \033[1m{$file}\033[0m updated");
                
            if(is_file($source_abs)){
                self::r_mkdir(dirname($target_abs));
                copy($source_abs, $target_abs);
            }elseif(is_dir($source_abs)){
                self::r_mkdir(dirname($target_abs));
            }
        }
        
        // sync from the new one to the old one
        foreach($files as $file => $args){
            if(!in_array($rule, $args['rules']))
                continue;
            
            $target_abs = $target . '/' . $file;
            $source_abs = $source . '/' . $file;
            
            $type = is_file($source_abs) ? 'File' : 'Folder';
            
            // file created
            if(!isset(self::$mod_files[$file]))
                self::echo(" - New $type \033[1m{$file}\033[0m created");
                
            if(is_file($source_abs)){
                self::r_mkdir(dirname($target_abs));
                copy($source_abs, $target_abs);
            }elseif(is_dir($source_abs)){
                self::r_mkdir($source_abs);
            }
        }
    }
    
    static function toNameSpace($str){
        $str = preg_replace('![^a-zA-Z0-9]!', ' ', $str);
        $str = ucwords($str);
        return str_replace(' ', '', $str);
    }
}

if(php_sapi_name() !== 'cli')
    Phun::close('Please execute the script via cli');
Phun::init();