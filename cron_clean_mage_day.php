<?php

//Choose level of cleaning
$log_rotate_magento_app_logs  = '1';
$clean_magento_reports        = '1';
$clean_magento_sessions_files = '1';
$clean_magento_log_php        = '1';
$parsed_magento_folders       = '';

function clean_log_tables()
{
    global $db;
    $tables = array(
        'aw_core_logger',
        'lengow_log',
        'dataflow_batch_export',
        'dataflow_batch_import',
        'log_customer',
        'log_quote',
        'log_summary',
        'log_summary_type',
        'log_url',
        'log_url_info',
        'log_visitor',
        'log_visitor_info',
        'log_visitor_online',
        'index_event',
        'report_event',
        'report_viewed_product_index',
        'report_compared_product_index',
        'catalog_compare_item',
        'catalogindex_aggregation',
        'catalogindex_aggregation_tag',
        'catalogindex_aggregation_to_tag'
    );
    try {
        $dbh = new PDO('mysql:host=' . $db['host'] . ';port=3306;dbname=' . $db['name'], $db['user'], $db['pass'], array(
            PDO::ATTR_PERSISTENT => false
        ));
        foreach ($tables as $v => $k) {
            echo "Running " . 'TRUNCATE `' . $db['pref'] . $k . '`' . "...\n";
            $stmt = $dbh->prepare('TRUNCATE `' . $db['pref'] . $k . '`');
            $stmt->execute();
            var_dump($stmt);
            echo date("r") . "\n";
        }
        
    }
    catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "\n";
        //die();
    }
    
}

//Clean only old reports
function clean_var_report_directory($magento_dir)
{
    if (is_dir($magento_dir . '/var/report')) {
        echo exec("find " . $magento_dir . "/var/report -type f -mmin +2500 -delete");
    }
}

//Logrotate of magento logs
function clean_var_log_directory($magento_dir)
{
    if (is_dir($magento_dir . '/var/log')) {
        
        $logrotate_mage_file = fopen("/tmp/logrotate_mage.conf", "w") or die("Unable to open file!");
        $txt = $magento_dir . "var/log/*.log {\n" . "daily\n" . "missingok\n" . "rotate 5\n" . "compress\n" . "notifempty\n" . "nocreate\n" . "su www-data www-data\n" . "sharedscripts\n}";
        
        fwrite($logrotate_mage_file, $txt);
        fclose($logrotate_mage_file);
        
        echo exec("/usr/sbin/logrotate -f /tmp/logrotate_mage.conf");
    }
}


exec('find -L /home/ /var/www/ -maxdepth 7 -path \'*/app/etc/*\' -name \'local.xml\'', $lines);

foreach ($lines as $value) {
    
    if (file_exists($value)) {
        $magento_dir = explode("app/etc/local.xml", $value);
        echo "\n \n--- Got another Magento website to clean " . $magento_dir[0] . "\n";
        // Load in the local.xml and retrieve the database settings
        $xml = simplexml_load_file($value);
        if (isset($xml->global->resources->default_setup->connection)) {
            
            $connection = $xml->global->resources->default_setup->connection;
            echo 'Host : ' . $connection->host[0] . "\n";
            echo 'Dbname : ' . $connection->dbname[0] . "\n";
            echo 'Username : ' . $connection->username[0] . "\n";
            echo 'Pwd : ' . $connection->password[0] . "\n";
            echo 'Prefix : ' . $connection->table_prefix[0] . "\n";
            $db['host'] = $connection->host[0];
            $db['name'] = $connection->dbname[0];
            $db['user'] = $connection->username[0];
            $db['pass'] = $connection->password[0];
            $db['pref'] = $connection->table_prefix[0];
            
            // Verify mysql connection
            try {
                $dbh = new PDO('mysql:host=' . $db['host'] . ';port=3306;dbname=' . $db['name'], $db['user'], $db['pass'], array(
                    PDO::ATTR_PERSISTENT => false
                ));
            }
            catch (PDOException $e) {
                print "Connect to MYSQL Error: !: " . $e->getMessage() . "\n";
                continue;
            }
            
            // Verify and run
            if (is_dir($magento_dir[0] . '/var/session')) {
                $parsed_magento_folders++;
                
                if ($clean_magento_sessions_files == '1') {
                    echo "Call clean session files \n";
                    echo exec("find " . $magento_dir[0] . "/var/session -type f -mmin +600 -delete");
                }
                
                echo "Call clean_log_tables() \n";
                clean_log_tables();
                
                if ($clean_magento_reports == '1') {
                    echo "Call clean_var_directory(" . $magento_dir[0] . ") \n";
                    clean_var_report_directory($magento_dir[0]);
                }
                
                if ($log_rotate_magento_app_logs == '1') {
                    echo "Call clean_var_log_directory(" . $magento_dir[0] . ") \n";
                    clean_var_log_directory($magento_dir[0]);
                }
                
                if (is_file($magento_dir[0] . '/shell/log.php') and $clean_magento_log_php == '1') {
                    echo " log.php exists \n";
                    echo exec("php -q " . $magento_dir[0] . "/shell/log.php clean status") . "\n";
                    echo exec("php -q " . $magento_dir[0] . "/shell/log.php clean --days 1") . "\n";
                    echo exec("php -q " . $magento_dir[0] . "/shell/log.php clean status") . "\n" . "\n";
                }
            } else
                echo "\n ! " . $magento_dir[0] . '/var/session doesnt exists' . "\n";
        }
    } else
        die('Unable to load Magento local xml File');
}
echo "\n End of script, parsed " . $parsed_magento_folders . " magento folders.\n";
