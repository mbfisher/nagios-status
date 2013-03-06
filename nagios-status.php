<?php

$config = array(
  'authenticate' => false,
  'user' => '',
  'password' => '',
  'status-file' => '/usr/local/nagios/var/status.dat',
  'status' => array(
    0 => 'OK',
    1 => 'WARNING',
    2 => 'CRITICAL',
    3 => 'UNKNOWN'
  ),
  'service-colour' => array(
    'ok' => 'darkgreen',
    'warning' => 'yellow',
    'critical' => 'red',
    'unknown' => 'orange'
  ),
  'exclude-hosts' => array( 'localhost' ),
  'service-size' => array(
    'ok' => 40,
    'unknown' => 60,
    'warning' => 85,
    'critical' => 125
  ),
  'service-position' => array(
    'left-min' => 100,
    'left-max' => 1600,
    'top-min' => 150,
    'top-max' => 700
  ),
  'animation' => array(
     'enable' => false,
     'speed' => 5
  )
);

class Host {
  public $services;

  static public function parse($f) {
    if ( preg_match_all('/\s*define host {(.+)}/s', $f, $matches) ) {
      foreach ( $matches as $match ) {
        $host = new Host();
        foreach ( explode("\n", $match) as $pair ) {
          list($k, $v) = array_map( 'trim', explode('=', $pair) );
          $host->$k = $v;
        }
      }
      $hosts[] = $host;
    }
    return isset($hosts) ? $hosts : array();
  }
}

class Service {
  public $host_name; // freedomclinics.com
  public $service_description; // Check /home disk
  public $modified_attributes; // 0
  public $check_command; // check_nrpe!geek_check_disk!40% 20% /home
  public $check_period; // 24x7
  public $notification_period; // 24x7
  public $check_interval; // 5.000000
  public $retry_interval; // 1.000000
  public $event_handler; // 
  public $has_been_checked; // 1
  public $should_be_scheduled; // 1
  public $check_execution_time; // 0.157
  public $check_latency; // 0.034
  public $check_type; // 0
  public $current_state; // 0
  public $last_hard_state; // 0
  public $last_event_id; // 93
  public $current_event_id; // 106
  public $current_problem_id; // 0
  public $last_problem_id; // 36
  public $current_attempt; // 1
  public $max_attempts; // 4
  public $state_type; // 1
  public $last_state_change; // 1349863977
  public $last_hard_state_change; // 1349863977
  public $last_time_ok; // 1350026877
  public $last_time_warning; // 0
  public $last_time_unknown; // 0
  public $last_time_critical; // 1349863677
  public $plugin_output; // DISK OK - free space: /home 3416 MB (89% inode
  public $long_plugin_output; // 
  public $performance_data; // /home
  public $last_check; // 1350026877
  public $next_check; // 1350027177
  public $check_options; // 0
  public $current_notification_number; // 0
  public $current_notification_id; // 47
  public $last_notification; // 0
  public $next_notification; // 0
  public $no_more_notifications; // 0
  public $notifications_enabled; // 1
  public $active_checks_enabled; // 1
  public $passive_checks_enabled; // 1
  public $event_handler_enabled; // 1
  public $problem_has_been_acknowledged; // 0
  public $acknowledgement_type; // 0
  public $flap_detection_enabled; // 1
  public $failure_prediction_enabled; // 1
  public $process_performance_data; // 1
  public $obsess_over_service; // 1
  public $last_update; // 1350026907
  public $is_flapping; // 0
  public $percent_state_change; // 0.00
  public $scheduled_downtime_depth; // 0
}

class NagiosStatus {
  public $config;

  public function __construct($config) {
    $this->config = $config;
  }
  public function parse($f) {
    $summary = $hosts = array();
    if ( preg_match_all('/(servicestatus|hoststatus) {([^}]+)}/s', $f, $matches) ) {
      foreach ( $matches[2] as $i => $match ) {
        $service = new Service();
        $hostname = 'unknown';
        foreach ( explode("\n", $match) as $pair ) {
          if ( !empty($pair) && strpos($pair, '=') > 0 ) {
            list($k, $v) = array_map( 'trim', explode('=', $pair) );
            $k == 'host_name' && $hostname = $v;
            if ( $k == 'current_state' && $matches[1][$i] == 'hoststatus' && $v > 0 ) $v = 2;
            $service->$k = $v;
          }
        }
        if ( !in_array($hostname, $this->config['exclude-hosts']) ) {
          $hosts[$hostname][] = $service;
          self::add_to_summary($summary, $service);
        }
      }
    }
    return array( $hosts, $summary );
  }
  
  public function add_to_summary(&$summary, $service) {
    !isset($summary[$service->current_state]) && $summary[$service->current_state] = 0;
    $summary[$service->current_state]++;
    $summary['state'] = !isset($summary['state']) || $service->current_state > $summary['state'] ? $service->current_state : $summary['state'];
  }

  public function background_color($state) {
    return $this->config['status-color'][$state];
  }

  public function left() {
    return rand($this->config['service-position']['left-min'], $this->config['service-position']['left-max']);
  }

  public function top() {
    return rand($this->config['service-position']['top-min'], $this->config['service-position']['top-max']);
  }

  public function auth() {
    if ( $this->config['authenticate'] ) {
      if ( 
        ( !isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != $this->config['user'] )
        ||
        ( !isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_PW'] != $this->config['password'] )
      ) {
        return false;
      }
    }

    return true;
  }
}

$ns = new NagiosStatus($config);

if ( !$ns->auth() ) {
  header('WWW-Authenticate: Basic realm="nagios-status"');
  header('HTTP/1.0 401 Unauthorized');
  echo "Unauthorized!";
  exit;
}

if ( !$f = file_get_contents($config['status-file']) ) {
  die("Could not read status file [{$config['status-file']}]");
}

list($hosts, $summary)= $ns->parse($f);

?>

<html>
  <head>
    <style type="text/css">
      html { z-index: -1; }
      #hosts { margin: 120px auto 0 auto; }
      .host { border-right: 1px solid white; float: left; }
      .service { border: 1px solid white; cursor: pointer; position: absolute; z-index: 0; }
      .service.ok { background-color: <?= $config['service-colour']['ok'] ?>; height: <?= $config['service-size']['ok'] ?>px; width: <?= $config['service-size']['ok'] ?>px; }
      .service.unknown { background-color: <?= $config['service-colour']['unknown'] ?>; <?= $config['service-size']['unknown'] ?>px; width: <?= $config['service-size']['unknown'] ?>px; z-index: 5; }
      .service.warning { background-color: <?= $config['service-colour']['warning'] ?>; height: <?= $config['service-size']['warning'] ?>px; width: <?= $config['service-size']['warning'] ?>px; z-index: 10; }
      .service.critical { background-color: <?= $config['service-colour']['critical'] ?>; height: <?= $config['service-size']['critical'] ?>px; width: <?= $config['service-size']['critical'] ?>px; z-index: 15; }
      .service:hover { border: 2px solid black; }
      .service.selected { border: 2px solid black; }
      .service-details { display: none; }
      #summary { border: 1px solid black; padding: 5px; position: absolute; right: 20px; top: 20px; }
      #summary .key { display: inline-block; width: 80px; }
      #summary .value { display: inline-block; text-align: right; width: 20px; }
      #service-details { position: absolute; left: 20px; top: 20px; }
      #service-details .key { display: inline-block; width: 80px; }
      #service-details .value { display: inline-block; width: 600px; }
      .pair .value { margin-left: 12px; }
    </style>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
    <script type="text/javascript">
      $(document).ready( function() {
        setTimeout( function() {
          document.location.reload(true);
        }, 60000 );
        $('.service').click( function(e) {
          e.stopPropagation();
          $('#service-details').html( $(this).find('.service-details').html() );
          $('.service').removeClass('selected');
          $(this).addClass('selected');
        });
        $('html').click( function() {
          $('#service-details').html('');
          $('.service').removeClass('selected');
        });

        if ( <?= (int) $config['animation']['enable'] ?> ) {
          var left_min = <?= $config['service-position']['left-min']; ?>;
          var left_max = <?= $config['service-position']['left-max']; ?>;
          var top_min = <?= $config['service-position']['top-min']; ?>;
          var top_max = <?= $config['service-position']['top-max']; ?>;

          $('.service.ok').each( function(i) {
            var la = Math.floor(Math.random()*(left_max-left_min)+left_min);
            var ta = Math.floor(Math.random()*(top_max-top_min)+top_min);
            var ln = Number($(this).css('left').replace('px', ''));
            var tn = Number($(this).css('top').replace('px', '')); 
            var distance = Math.floor(Math.sqrt( Math.pow(Math.abs(la-ln), 2) + Math.pow(Math.abs(ta-tn), 2) ));
            var speed = <?= $config['animation']['speed']; ?>;
            var time = Math.floor( distance / (speed/1000) );
            console.log('['+[la+','+ln, ta+','+tn, distance, time].join('][')+']');
            $(this).animate({ left: la, top: ta }, time, 'linear');
          });
        }
      });
    </script>
  </head>
  <body>
    <div id="service-details"></div>
    <div id="hosts">
      <?php $i = 0; ?>
      <?php foreach ( $hosts as $hostname => $services ) { ?>
        <!--<div class="host">-->
          <?php foreach ( $services as $s => $service ) { ?>
            <div id="service-<?= $i ?>" class="service <?= strtolower($config['status'][$service->current_state]) ?>" style="left: <?php echo $ns->left() ?>px; top: <? echo $ns->top() ?>px">
              <div class="service-details">
                <div class="pair"><span class="key">Host</span><span class="value"><?= $service->host_name ?></span></div>
                <div class="pair"><span class="key">Description</span><span class="value"><?= $service->service_description ?></span></div>
                <div class="pair"><span class="key">Output</span><span class="value"><?= $service->plugin_output ?></span></div>
                <div class="pair"><span class="key">State</span><span class="value"><?= $config['status'][$service->current_state] ?></span></div>
              </div><!-- END service-details -->
            </div><!-- END service -->
          <?php } ?>
        <!--</div>--><!-- END host -->
      <?php } ?>
    </div><!-- END hosts -->
    <div style="clear: both"></div>
    <div id="summary">
      <?php foreach ( $config['status'] as $status => $name ) { ?>
        <div class="pair"><span class="key"><?= $name ?></span><span class="value"><?php if ( isset($summary[$status]) ) { echo $summary[$status]; } else { echo 0; } ?></span></div>
      <?php } ?>
    </div>
  </body>
</html>
