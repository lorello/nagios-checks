<?php
# TODO: 
# - manage multiple warn/criticals on different values monitored
# - add parameters for warn and crit values
#

# disable cacheing on private/shared caches (varnish?)
session_cache_limiter('nocache');
session_start();

$result = "OK -";
$status = opcache_get_status();
$config = opcache_get_configuration();

$check_status[0]= 'OK';
$check_status[1]= 'WARNING';
$check_status[2]= 'CRITICAL';
$check_status[3]= 'UNKNOWN';

$size = $config['directives']['opcache.memory_consumption'];
$size_mb = $config['directives']['opcache.memory_consumption'] / 1024 / 1024;
$is_enabled = $config['directives']['opcache.enable'];
$basic_info = $config['version']['opcache_product_name'].' '.$config['version']['version'];

$start_time = $status['opcache_statistics']['start_time'];
# number of seconds after the start of opcache where values are considered not interesting
$start_time_unknown_period = 300;
# debug echo "$start_time + $start_time_unknown_period ? ".time(). "\n";

$result_status = 0;
$result_message = "OK - $basic_info {$size_mb}MB";

if ($is_enabled != 1)
{
    $result_status = 3;
    $result_message = "UNKNOWN - $basic_info is not enabled";
}

if ( ($start_time + $start_time_unknown_period) > time() ) {
    $result_status = 3;
    $result_message = "UNKNOWN - $basic_info started a few seconds ago";
}

if ($status['restart_pending']) {
    $result_status = 3;
    $result_message = "UNKNOWN - $basic_info will restart soon";
}
if ($status['restart_in_progress'] ) {
    $result_status = 3;
    $result_message = "UNKNOWN - $basic_info restarting";
}

$values['size']         = $size;
$values['enabled']      = $status['opcache_enabled'];
$values['cache_full']   = $status['cache_full'];

# From memory_usage
$values['used_memory']                  = $status['memory_usage']['used_memory'];
$values['free_memory']                  = $status['memory_usage']['free_memory'];
$values['wasted_memory']                = $status['memory_usage']['wasted_memory'];
$values['current_wasted_percentage']    = $status['memory_usage']['current_wasted_percentage'];

# From opcache_statistics
$values['num_cached_scripts']   = $status['opcache_statistics']['num_cached_scripts'];
$values['num_cached_keys']      = $status['opcache_statistics']['num_cached_keys'];
$values['max_cached_keys']      = $status['opcache_statistics']['max_cached_keys'];
$values['hits']                 = $status['opcache_statistics']['hits'];
$values['oom_restarts']         = $status['opcache_statistics']['oom_restarts'];
$values['hash_restarts']        = $status['opcache_statistics']['hash_restarts'];
$values['manual_restarts']      = $status['opcache_statistics']['manual_restarts'];
$values['misses']               = $status['opcache_statistics']['misses'];
$values['blacklist_misses']     = $status['opcache_statistics']['blacklist_misses'];
$values['blacklist_miss_ratio'] = $status['opcache_statistics']['blacklist_miss_ratio'];
$values['opcache_hit_rate']     = $status['opcache_statistics']['opcache_hit_rate'];

$memory_usage_perc      = $values['used_memory'] / $size;
#echo $memory_usage_perc;
$memory_usage_perc_warn = 0.8;
$memory_usage_perc_crit = 0.9;
if ($memory_usage_perc > $memory_usage_perc_crit) {
  $result_status    = 2;
  $result_message   = "CRITICAL - $basic_info memory usage percentage is over critical level ".($memory_usage_perc*100).'%';
} elseif ($memory_usage_perc > $memory_usage_perc_warn) {
  $result_status    = 1;
  $result_message   = "WARNING - $basic_info memory usage percentage is over warning level ".($memory_usage_perc*100).'%';
}
$opcache_hit_rate       = $values['opcache_hit_rate'];
$opcache_hit_rate_warn  = 0.8;
$opcache_hit_rate_crit  = 0.9;
if ($opcache_hit_rate < $opcache_hit_rate_crit) {
  $result_status    = 2;
  $result_message   = "CRITICAL - $basic_info memory hitrate is under critical level ".$opcache_hit_rate.'%';
} elseif ($memory_usage_perc > $memory_usage_perc_warn) {
  $result_status    = 1;
  $result_message   = "WARNING - $basic_info memory hitrate is under warning level ".$opcache_hit_rate.'%';
}


# Printing results
$results=array();
foreach($values as $label=>$value) {
    $results[] = "$label=$value";
}

/**
 * Standard Nagios output - multiline with perfdata
 *[OK|WARNING|CRITICAL|UNKNOWN] - DataSource1: $type $name $active/max, DataSource2: $type $name $active/$max | $active;$active_warn;$active_crit;$graph_min;$graph_max
 Threads WAITING=34, RUNNABLE=23, TIMED_WAITING=2, .... $thread_state=$threads_number
 Memory: $heap_used/$heap_size| threads_Waiting=30;40;50 threads_runnable=12;40;50
 memory_heap_used=$heap_used;$heap_size_warn;$heap_size_warn
 * */

echo $result_message . ' | ';
echo implode(' ', $results);

#echo "<pre>";
#echo print_r(opcache_get_status());
#echo "<hr />";
#echo print_r(opcache_get_configuration());
