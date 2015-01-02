<?php

/**
 * @file
 * Contains \Drupal\webfact\Controller\WebfactController
 */


#use GuzzleHttp\Exception\ClientException;
#use GuzzleHttp\Exception\RequestException;
#use Monolog\Logger;
#use Monolog\Handler\StreamHandler;

use Docker\Http\DockerClient;
#use Docker\Docker;


/*
 * encapsulate the Webfactory stuff into a calls, for easier Drupal7/8 porting
 */
class WebfactController {
  protected $client, $hostname, $nid, $id, $markup, $result, $status, $docker;
  protected $docker_start_vol, $config, $user, $website, $des;
  protected $verbose;
  protected $cont_image, $dserver, $fserver, $loglines, $env_server; // settings


  public function __construct() {
    global $user;
    $account = $user;
    $this->user= $account->name;
    $this->markup = '';
    $this->verbose = 1;
    #watchdog('webfact', 'WebfactController __construct()');

    # Load configuration, override in settings.php
    $this->cont_image= variable_get('webfact_cont_image', 'boran/drupal');
    $this->dserver   = variable_get('webfact_dserver', 'tcp://mydockerserver.example.ch:2375');
    $this->fserver   = variable_get('webfact_fserver', 'mywildcard.example.ch');
    $this->rproxy    = variable_get('webfact_rproxy', 'nginxproxy');
    $this->loglines  = variable_get('webfact_loglines', 100);
    $this->env_server  = variable_get('webfact_env_server');

    /*$log = new Logger('name');
      $log->pushHandler(new StreamHandler('/tmp/mono.log', Logger::WARNING));
      $log->addWarning('Foo');
      $log->addError('Bar');*/

    $destination = drupal_get_destination();
    $this->des = '?destination=' . $destination['destination']; // remember where we were

    try {
      #todo: $client = new Docker\Http\DockerClient(array(), $this->dserver);
      $this->client = new Docker\Http\DockerClient(array(), 'tcp://195.176.209.22:2375');
      $this->docker = new Docker\Docker($this->client);
#$client = new Docker\Http\DockerClient(array(), 'http://195.176.209.22:2375');
#$docker = new Docker\Docker($client);
#$manager = $docker->getContainerManager();
#$container = $manager->find('vanilla1');
#$manager->stop($container);
#print_r($container->getName());
#print_r($container->getRuntimeInformations()['State']);

      #dpm($this->docker);
    }
    catch (RequestException $e) {
      dpm($e);
      $this->message('Unknown http RequestException to docker', 'warning');
    }
  }

  public function helloWorldPage() {   // nostalgia: the first function
    #dpm('helloWorldPage');
    return array('#markup' => '<p>' . t('Hello World') .  '</p>');
  }

  public function message($msg, $status='status') {
    if ($this->verbose == 1) {
       drupal_set_message($msg, $status);
    }
    // else stay silent
  }


  protected function imageAction($verbose=1) {
    //watchdog('webfact', "contAction() $this->action");
    try {
      $manager = $this->docker->getImageManager(); 

      if ($this->action=='images') {
        #$imagemgt = $this->docker->getImageManager();   // todo
        $response = $this->client->get(["/images/json?all=0",[]]);
        $this->markup = "<pre>Docker Images:\n" ;
          $procarray    = $response->json();
          $this->markup .= "Created     Id\t\t\t\t\t\t\t              Size\t Tag\n";
          foreach ($procarray as $row) {
            //$this->markup .= print_r($row, true);
            $this->markup .= "${row['Created']}  ${row['Id']}  ${row['Size']}\t " . $row['RepoTags'][0] . "\n";
            //$this->markup .= $row['RepoTags'][0] . "\t ${row['Size']}\t ${row['Created']} \n";
          }
        $this->markup .= "</pre>" ;
        return;
      }

      else if ($this->action=='events') {
        // todo: hangs and gives nothing back
        //$response = $this->client->get(["/events",[]]);
        $response = $this->client->get(["/events?since=2014-12-23",[]]);
        //$response = $this->client->get("/events?filter{'container':'starterkit'}");
        $this->markup = 'Docker events: <pre>' .$response->getBody() . '</pre>';
      }

      else if ($this->action=='version') {
        $response = $this->client->get(["/version",[]]);
        $this->markup = '<pre>' . $response->getBody() .'</pre>';
      }

    } catch (Exception $e) {
      #echo '<pre>' . print_r($e, true) .'</pre>';
      $this->message($e->getResponse()->getReasonPhrase() .
        " " . $e->getResponse()->getStatusCode(), 'warning');
      //$this->message('Cannot connect to docker: RequestException '. $e->getRequest(), 'warning');
      if ($e->hasResponse()) {
        $this->message('Response: ' . $e->getResponse(), 'warning');
      }
    }
  }


  protected function contAction($verbose=1) {
    //watchdog('webfact', "contAction() $this->action");
    try {
      $manager = $this->docker->getContainerManager(); 
      $container = $manager->find($this->id);

      if ($this->action=='delete') {
        if (! $container) {
          $this->message("$this->id does not exist");
        }
        else if ($container->getRuntimeInformations()['State']['Running'] == TRUE) {
          $this->message("$this->id must be stopped first");
        }
        else {
          $this->message("$this->action $this->id");
          $manager->remove($container);
          watchdog('webfact', "$this->action $this->id ", array(), WATCHDOG_NOTICE);
        }
        return;
      }

      else if ($this->action=='containers') {
        $this->markup = "<pre>Running containers:\n" ;
        $this->markup .= "Name\t\tImage\t\t\t Running?\t StartedAt\n";
        $containers = $manager->findAll();
        foreach ($containers as $container) {
          $manager->inspect($container);
          #dpm($container->getID() . " " . $container->getName() . "\n");
          $this->markup .= $container->getName() . "\t " . $container->getImage()
            . "\t " . $container->getRuntimeInformations()['State']['Running']
            . "\t " . $container->getRuntimeInformations()['State']['StartedAt']
          #  . "\t " . $container->getCmd()
          #  . "\t " . $container->getID()
            . "\n";
        }
        $this->markup .= "</pre>" ;
        return;
      }

      else if ($this->action=='inspect') {
        if (! $container) {
          $this->message("$this->id does not exist");
        }
        else {
          $this->message("$this->action $this->id");
          //watchdog('webfact', "$this->action $this->id ", array(), WATCHDOG_NOTICE);
          //$manager->inspect($container);
          $cont=$container->getRuntimeInformations();
          $this->markup  = '<pre>';
          $this->markup .="Hostname:" . $cont['Config']['Hostname'] .'<br>';

          if ($cont['State']['Paused']==1) {
            $this->markup .="Status: paused " .'<br>';
            $this->status = 'paused';
          }
          else if ($cont['State']['Running']==1) {
            $this->markup .="Status: Running since " .  $cont['State']['StartedAt'] .'<br>';
            $this->status = 'running';
          }
          else if ($cont['State']['Restarting']==1) {
            $this->markup .="Status: Restarting " .'<br>';
            $this->status = 'restarting';
          }
          else {
            //dpm($cont['State']);
            $this->markup .="Status: STOPPED" .'<br>';
            $this->status = 'stopped';
          }
          $this->markup .='<br>';
          $this->markup .="Created "    . $cont['Created'] .'<br>';
          $this->markup .="FinishedAt " . $cont['State']['FinishedAt'] .'<br>';
          $this->markup .="ExitCode "   . $cont['State']['ExitCode'] .'<br>';
          $this->markup .='<br>';

          $this->markup .="Base image " . $cont['Config']['Image'] .'<br>';
          if (isset($cont['Config']['Env'])) {
            #$this->markup .="Environment " . print_r($cont['Config']['Env'], true) .'<br>';
            $this->markup .="<br>Environment: (excluding password entries)<br>";
            sort($cont['Config']['Env']);
            foreach($cont['Config']['Env'] as $envline) {
              // hide variables that might containing passwords
              if (! preg_match('/$envline|_PW|_GIT_REPO/', $envline)) {
                $this->markup .= $envline .'<br>';
              }
            }
            $this->markup .="<br>";
          }
          if (empty($cont['Volumes'])) {
            $this->markup .="Volumes: none <br>";
          }
          else {
            $this->markup .="Volumes " . print_r($cont['Volumes'], true) .'<br>';
            # todo: print array paired elements on each line
            #$this->markup .="Volumes:<br>";
            #foreach($cont['Volumes'] as $line) {
            #  $this->markup .=$line .'<br>';
            #}
            #$this->markup .="<br>";
          }

          if (isset($cont['HostConfig']['PortBindings'])) {
            $this->markup .="PortBindings " . print_r($cont['HostConfig']['PortBindings'], true) .'<br>';
          }
          else {
            $this->markup .="PortBindings: none <br>";
          }

          $this->markup .="Container id: " . $cont['Id'] .'<br>';
          //$this->markup .="RestartPolicy " . print_r($cont['HostConfig']['RestartPolicy'], true);
          //$this->markup .="Network " . print_r($cont['NetworkSettings'], true);

          $this->markup .= '</pre>';
        }
        return;
      }


      else if ($this->action=='processes') {
        if (! $container) {
          $this->message("$this->id does not exist");
        }
        else {
          //$procs=$manager->top($container, "aux");
          //todo: dont use the library api, as titles are hard to extract
          $response = $this->client->get(['/containers/{id}/top', [ 'id' => $this->id, ]]);
          $procarray      = $response->json();
          $this->markup = '<pre>';
          for ($i = 0; $i < count($procarray['Titles']); ++$i) {
            $this->markup .= $procarray['Titles'][$i] . ' ';
          }
          $this->markup .= "\n";
          foreach ($procarray['Processes'] as $process) {
            for ($i = 0; $i < count($process); ++$i) {
              $this->markup .= $process[$i] . ' ';
            }
            $this->markup .= "\n";
          }
          $this->markup .= '</pre>';
        }
        return;
      }

      else if ($this->action=='changes') {
        if (! $container) {
          $this->message("$this->id does not exist");
        }
        else {
          $logs=$manager->changes($container);
          $this->markup = '<pre>';
          $this->markup .= "Kind  Path<br>";
          foreach ($logs as $log) {
            $this->markup .= $log['Kind'] . " " . $log['Path'] . "<br>";
          }
          $this->markup .= '</pre>';
        }
        return;
      }

      else if ($this->action=='logs') {
        if (! $container) {
          $this->message("$this->id does not exist");
        }
        else {
          //                               $follow $stdout $stderr $timestamp $tail = "all"
          $logs=$manager->logs($container, false, true, true, false, $this->loglines);
          //$logs=$manager->logs($container, true, true, true, false, $this->loglines);
          $this->markup = '<pre>';
          foreach ($logs as $log) {
            $this->markup .= $log['output'];
          }
          $this->markup .= '</pre>';
        }
        return;
      }
 
      else if ($this->action=='start') {
        if (! $container) {
          $this->message("$this->id does not exist");
        } 
        else if ($container->getRuntimeInformations()['State']['Running'] == TRUE) {
          $this->message("$this->id already started");
        }
        else {
          $this->message("$this->action $this->id");
          $hostconfig=[
             // ensure containers start on boot. todo: make it setting per container?
            'RestartPolicy'=> [ 'MaximumRetryCount'=>3, 'Name'=>'always' ],
            'Binds'=> $this->docker_start_vol,
            //todo 'PortBindings' => [ '80/tcp' => [ [ 'HostPort' => $this->port=$id;
          ] ;
          $manager->start($container, $hostconfig);
        }
        return;
      }

      else if ($this->action=='rebuild') {
        // stop, delete: ignore warning of container does not exist, or is stopped
        if (! $container) {
          $this->message("$this->id does not exist");
          return;
        }
        watchdog('webfact', "rebuild - stop, delete, create", WATCHDOG_NOTICE);
        $manager->stop($container);
        $this->message("rebuild - stopped");
        $manager->remove($container);
        $this->message("rebuild - deleted");

        $this->action='create';
        $this->contAction();
        $this->message("Click on 'logs' to track progress", 'status');
        watchdog('webfact', "rebuild - finished", WATCHDOG_NOTICE);
        //$this->action='logs';
        //$this->contAction();
        return;
      }

      else if ($this->action=='create') {
        $fqdn=$this->id . '.' . $this->fserver;  // e.g. MYHOST.webfact.example.ch
        $this->docker_start_vol=array();
        $docker_vol=array();
        $docker_env=array();

        // Initial docker environment variables
        // todo: should only be for Drupal sites?
        $docker_env = ['DRUPAL_SITE_NAME='   . $this->website->title,
          'DRUPAL_SITE_EMAIL='  . $this->website->field_site_email['und'][0]['safe_value'],
          'DRUPAL_ADMIN_EMAIL=' . $this->website->field_site_email['und'][0]['safe_value'],
          "VIRTUAL_HOST=$fqdn",
        ];
        // Load term: send the category to the container environment
        if (isset($this->website->field_category['und'][0]['tid'])) {
          $category=taxonomy_term_load($this->website->field_category['und'][0]['tid']);
          $docker_env[] = "WEBFACT_CATEGORY=$category->name";
        }
        if (isset($this->env_server)) {     // pull in default env 
          $docker_env[] = $this->env_server;
        }

        // Load the associated template
        if (!isset($this->website->field_template['und'][0]['target_id'])) {
            $this->message("A template must be associated with $this->id", 'error');
            return;
        }
        $tid=$this->website->field_template['und'][0]['target_id'];
        if ($tid==null) {
            $this->message("invalid template associated with $this->id", 'error');
            return;
        }
        $template=node_load($tid);
        if ($template==null) {
            $this->message("Template id $tid cannot be loaded", 'error');
            //dpm($template);
            return;
        }
        // use the template or website specified docker image?
        if (!empty($template->field_docker_image['und'][0]['safe_value']) ) {
          $this->cont_image = $template->field_docker_image['und'][0]['safe_value'];
          //dpm('Using template image: ' . $this->cont_image);
        }
        if (!empty($this->website->field_docker_image['und'][0]['safe_value']) ) {
          $this->cont_image = $this->website->field_docker_image['und'][0]['safe_value'];
          //dpm('Using website image: ' . $this->cont_image);
        }

        ## pull in template environment key/value array
        if (!empty($template->field_docker_environment['und']) ) {
          foreach ($template->field_docker_environment['und'] as $row) {
            #dpm($row['safe_value']);
            if (!empty($row['safe_value'])) {
              $docker_env[]= $row['safe_value'];
            }
          }
        }
        // website level environment
        if (!empty($this->website->field_docker_environment['und']) ) {
          foreach ($this->website->field_docker_environment['und'] as $row) {
            #dpm($row['safe_value']);
            if (!empty($row['safe_value'])) {
              $docker_env[]= $row['safe_value'];
            }
          }
        }
        sort($docker_env);
        #dpm($docker_env);

        if (!empty($template->field_docker_volumes['und']) ) {
          foreach ($template->field_docker_volumes['und'] as $row) {
            //dpm($row);
            if (!empty($row['safe_value'])) {
              # image "create time" mapping: foo:bar:baz, extract the foo:
              $count = preg_match('/^(.+)\:.+:/', $row['safe_value'], $matches);
              #dpm($matches);
              if (isset($matches[1])) {
                # $docker_vol = ["/root/gitwrap/id_rsa" =>"{}", "/root/gitwrap/id_rsa.pub" =>"{}" ];
                $docker_vol[] .= "$matches[1] =>{}";
                # runtime mapping
                $this->docker_start_vol[] .= $row['safe_value'];
              } 
              else {
                $this->message("Template volume field must be of the form xx:y:z", 'error');
                return;
              }
            }
          }
        }
        // website level containers
        if (!empty($this->website->field_docker_volumes['und']) ) {
          foreach ($this->website->field_docker_volumes['und'] as $row) {
            if (!empty($row['safe_value'])) {
              # image "create time" mapping: foo:bar:baz, extract the foo:
              $count = preg_match('/^(.+)\:.+:/', $row['safe_value'], $matches);
              if (isset($matches[1])) {
                $docker_vol[] .= "$matches[1] =>{}";
                # runtime mapping
                $this->docker_start_vol[] .= $row['safe_value'];
              }
              else {
                $this->message("Template volume field must be of the form xx:y:z", 'error');
                return;
              }
            }
          }
        }
        #dpm($docker_vol);
        #dpm($this->docker_start_vol);

        // create the container
        $config = ['Image'=> $this->cont_image, 'Hostname' => $fqdn,
                   'Env'  => $docker_env,       'Volumes'  => $docker_vol
          ];
        $container= new Docker\Container($config);
        $container->setName($this->id);
        $manager->create($container);
        $msg= "$this->action $this->id: title="
          . $this->website->title . ", email=" . $this->website->field_site_email['und'][0]['safe_value']
          . ", docker image=$this->cont_image" ;
        $this->message($msg, 'status');
        watchdog('webfact', $msg);

        // created, so now start it:
        sleep(2);   // seems to need some time if volumes need to be mounted
        $this->action='start';
        $this->contAction();
        // inform user:
        $cur_time=date("Y-m-d H:i:s");  // calculate now + 6 minutes
        $newtime=date('H:i', strtotime('+6 minutes', strtotime($cur_time))); // todo setting
        $this->message("Provisioning: you can connect to the new site at $newtime.", 'status');

        return;
      }

      else if ($this->action=='kill') {
        if (! $container) {
          $this->message("$this->id does not exist");
        } 
        else if ($container->getRuntimeInformations()['State']['Running'] == FALSE) {
          $this->message("$this->id already stopped");
        }
        else {
          $this->message("$this->action $this->id");
          $manager->kill($container);
        }
        return;
      }

      else if ($this->action=='restart') {
        if (! $container) {
          $this->message("$this->id does not exist");
        } 
        else {
          $this->message("$this->action $this->id");
          $manager->restart($container);
          #dpm($container->getRuntimeInformations()['State']['StartedAt']);
        }
        return;
      }

      else if ($this->action=='stop') {
        if (! $container) {
          $this->message("$this->id does not exist");
        } 
        else if ($container->getRuntimeInformations()['State']['Running'] == FALSE) {
          $this->message("$this->id already stopped");
        } 
        else {
          $this->message("$this->action $this->id");
          $manager->stop($container);
          #$manager->inspect($container);
          #dpm($container->getRuntimeInformations()['State']);
        }
        #dpm($container->getId() . ' ' . $container->getExitCode());
        return;
      }

      else if (($this->action=='pause') || ($this->action=='unpause')) {
        $this->message("$this->action $this->id : not implemented");
        watchdog('webfact', "$this->action $this->id : todo: not implemented", array(), WATCHDOG_NOTICE);
        return;
      }

      else {  // wait
        #$response = $this->client->post("$this->dserver/containers/$this->id/$this->action");
        #watchdog('webfact', "$this->action $this->id result:" . $response->getStatusCode(), array(), WATCHDOG_NOTICE);
        watchdog('webfact', "unknown action $this->action on $this->id :", array(), WATCHDOG_NOTICE);
      }


    // Exception handling: todo: old code from guzzle, needs a clean
    } catch (ClientException $e) {
      $this->result=$e->getResponse()->getStatusCode();
      #$this->message('Unknown http error to docker', 'warning');
      $this->message("$this->action container $this->id: "
        . $e->getResponse()->getBody() . ' code:'
        . $e->getResponse()->getStatusCode() , 'warning');

      #echo '<pre>RequestException Request: ' . $e->getRequest() .'</pre>';
        #echo '<pre>Response ' . $e->getResponse() .'</pre>';
        #echo '<pre>Response ' . $e->getResponse()->getBody() .'</pre>';
        #echo '<pre>' . print_r($cont, true) .'</pre>';

    } catch (ServerException $e) {
      $this->message($e->getResponse()->getBody(), 'warning');
      #$this->message('Unknown docker ServerException error', 'warning');

    } catch (RequestException $e) {
#      $this->message($e->getResponse()->getBody(), 'warning');
      $this->message('Unknown RequestException to docker', 'warning');

    } catch (Exception $e) {
      #echo '<pre>' . print_r($e, true) .'</pre>';
      // todo: Call to a member function getReasonPhrase() on a non-object
      $this->message($e->getResponse()->getReasonPhrase() .
        " " . $e->getResponse()->getStatusCode(), 'warning');
      //$this->message('Cannot connect to docker: RequestException '. $e->getRequest(), 'warning');
      if ($e->hasResponse()) {
        $this->message('Response: ' . $e->getResponse(), 'warning');
      }
    }
  }


 /**
   * This callback is mapped to the path
   * 'website/{action}/{id}'.
   *
   * @param string $action
   *   Container Action: add/delete/stop/start/..
   * @param string $id
   *   Node is to derive the name
   */
  public function arguments($action, $id, $verbose=1) {
    $list=array();

    if  (!is_numeric($id)) {
      $this->message("$this->action container: node invalid node id $this->nid", 'error');
      return;
    }
    #$this->message("arguments $action, $id");
    $this->action = $action;  // todo: only allow alpha
    $this->verbose=$verbose;  // todo: remove param, just use setting
    $this->nid=$id;

    //$this->client->setDefaultOption('timeout', 20);  //todo: parameter
    $this->result=-1;     // default: fail
    $this->status='n/a';  // default: unknown

    try {
      $manager = $this->docker->getContainerManager(); 
      switch ($action) {
        // container operations must have a node and container
        case 'advanced':
        case 'wait':
        case 'changes':
        case 'events':
        case 'inspect':
        case 'stop':
        case 'start':
        case 'delete':
        case 'restart':
        case 'pause':
        case 'kill':
        case 'unpause':
        case 'rebuild':
        case 'logs':
        case 'processes':
          $container = $manager->find($this->id);
          // get the node and find the container name
          $this->website=node_load($this->nid);
          //dpm($this->website);
          if ($this->website==null) {
            $this->message("$this->action container: node $this->nid not found", 'error');
            return;
          }
          if ($this->website->type!='website') {
            $this->message("$this->action container: node $this->nid is not a website (it is type $this->website->type)", 'error');
            return;
          }
          if (empty($this->website->field_hostname['und'][0]['safe_value']) ) {
            $this->message("$this->action container: node $this->nid, hostname is not set ", 'error');
            return;
          }
          $this->id=$this->website->field_hostname['und'][0]['safe_value'];
          $owner=$this->website->name;
          break;
      }
    } catch (RequestException $e) {
#      $this->message($e->getResponse()->getBody(), 'warning');
      $this->message('Unknown http RequestException to docker', 'warning');
    } catch (Exception $e) {
      #echo '<pre>' . print_r($e, true) .'</pre>';
      $this->message('Cannot connect to docker: RequestException '. $e->getRequest(), 'warning');
      if ($e->hasResponse()) {
        $this->message('Docker Response: ' . $e->getResponse());
      }
    }


    // check permission, call action, handle feedback
    switch ($action) {

      // image management
      case 'events':   // todo: just hangs
        if ( ! user_access('manage containers')) {
          $this->message("Permission denied, $this->user is not admin", 'error');
          break;
        }
      case 'images':  
      case 'version':
        $result=$this->imageAction();
        if ($verbose==0) {
          $render_array['webfact_arguments'][0] = array(
            '#result' => $this->result,
            '#status' => $this->status,
          );
          return $render_array;  // dont sent back any html
        }
        break;


      // image management
      case 'wait':       // Block until container id stops, then return the exit code. TODO: broken
        $this->client->setDefaultOption('timeout', 25);
      case 'changes':
        if ( ! user_access('manage containers')) {
          $this->message("Permission denied, $this->user is not admin", 'error');
          break;
        }
      case 'inspect':  
        // todo: should only be for the owner but views needs to be able to query the status
        // todo   $this->client->setDefaultOption('timeout', 100);
        $result=$this->contAction();
        if ($verbose==0) {
          $render_array['webfact_arguments'][0] = array(
            '#result' => $this->result,
            '#status' => $this->status,
          );
          return $render_array;  // dont sent back any html
        }
        break;

      // the following are immediate actions, redirected back to the caller page
      case 'stop':
      case 'start':
      case 'delete':
      case 'restart':
      case 'pause':
      case 'kill':
      case 'unpause':
      case 'create':
      case 'rebuild':
        if (($this->user!=$owner) && (! user_access('manage containers')  )) {
          $this->message("Permission denied, $this->user is not the owner ($owner) or admin", 'error');
          break;
        }
        $result=$this->contAction();
        if (isset($_GET['destination'])) {  // go back where we were
          #dpm(request_uri());
          $from = drupal_parse_url($_GET['destination']);
          drupal_goto($from['path']);  
        }
        break;

      case 'logs':
      case 'processes':
        if (($this->user!=$owner) && (!user_access('manage containers')  )) {
          $this->message("Permission denied, $this->user is not the owner ($owner) or admin", 'error');
          break;
        }
      case 'containers':
        $result=$this->contAction();
        break;

      case 'restartproxy':
        //$this->list[] = "Restart reverse proxy";
        watchdog('webfact', 'Restart reverse proxy');
        // The reverse proxy needs to re-discover
        $this->id=$this->rproxy;
        $this->action='restart';
        $this->contAction();
        if (isset($_GET['destination'])) {  // go back where we were
          #dpm(request_uri());
          $from = drupal_parse_url($_GET['destination']);
          drupal_goto($from['path']);  
        }
        break;

      case 'advanced':  // just drop through to menu below
        break;
      default:
        //$this->list[] = t("Unknown action @act", array('@act' => $action));
        //$this->list[] = t("ID  @id.", array('@id' => $id));
        break;
    }
    #watchdog('webfact', "arguments $action, $this->id $this->nid $owner");


    // quick links to actions
    // todo: what is the right (secure) way to create links?
    $wpath='/website';
    $destination = drupal_get_destination();
    $des = '?destination=' . $destination['destination']; // remember where we were
    if ($action != 'version') {
      $list[] = "Visit the destination website: "
        . " <a target=_blank href=http://$this->id.$this->fserver>http://$this->id.$this->fserver</a> "
        . " or the <a target=_blank href=http://$this->id.$this->fserver/server-status>apache status page</a>"
        ;
      $list[] ="Manage: "
        . " <a href=$wpath/stop/$this->nid>stop</a> | "
        . " <a href=$wpath/start/$this->nid>start</a> | "
        . " <a href=$wpath/logs/$this->nid>logs</a> | "
        . " <a href=$wpath/create/$this->nid>create</a> | "
        . " <a href=$wpath/delete/$this->nid onclick=\"return confirm('Are you sure?')\" >delete</a> "
        ;
      if ($this->nid && $this->website) {
      $list[] ="Advanced: "
        . " <a href=/node/$this->nid/edit$des>Edit meta data</a> | "
        . " <a href=$wpath/restart/$this->nid>restart</a> | "
        . " <a href=$wpath/kill/$this->nid>kill</a> | "
        . " <a href=$wpath/logs/$this->nid>logs</a> | "
        . " <a href=$wpath/inspect/$this->nid>inspect</a> or "
        . " <a href=$wpath/rebuild/$this->nid onclick=\"return confirm('Stop, delete, and recreate " . $this->website->title . ". Are you sure you sure?')\">full rebuild</a> "
        ;
      }

    }

    ## Admin menu
    if ( user_access('manage containers')) {
      // Load the associated template
      if (isset($this->website->field_template['und'][0]['target_id'])) {
        $tid=$this->website->field_template['und'][0]['target_id'];
      }

      $tlink='';
      if (isset($tid)) { $tlink=" <a href=/node/$tid/edit$des>edit template</a> | "; }
      $list[] = "Admin only: "
        #todo tid not always valid  
        . $tlink
        . " <a href=$wpath/processes/$this->nid>container processes</a> | "
        . " <a href=$wpath/changes/$this->nid>filesystem changes</a> | "
      ;

      $list[] = "Admin docker: query <a href=$wpath/version/$this->nid>version</a> | "
        // todo: some actions should not require a second id parameter
        //. " <a href=$wpath/events/$this->nid>events</a>| "
        . " <a href=$wpath/images/$this->nid>list images</a> | "
        . " <a href=$wpath/containers/$this->nid>list running containers</a> | "
        . " <a href=$wpath/restartproxy/0$this->des>restartproxy</a>"
      ;
    }


    // send back the HTML to be shown on the page
    $render_array['webfact_arguments'] = array();
    $fqdn="http://$this->id.$this->fserver";
    $description='';
    if ($this->website) {
      $description.='<h3>' . $this->website->title . '</h3>'
       . "<p>Owner: " . $this->website->name . ".</p>"
       #. ", website: <a target=_blank href=$fqdn>$fqdn</a>"
      ;
      if (isset($this->website->body['und'][0]['safe_value'])) {
        $description.= '<p>' . $this->website->body['und'][0]['safe_value'] .'</p>';
      }
    }
    $description.= '<p>Next actions:</p>';
    $render_array['webfact_arguments'][0] = array(
      '#type' => 'markup',
      '#markup' => $description,
    );

    //$title= empty($this->id) ? t('Actions') : $this->id ;
    $render_array['webfact_arguments'][1] = array(
      //'#title' => $title,
      '#theme' => 'item_list', // theme function to apply to the #items
      '#items' => $list,
      '#result' => $this->result,
      '#status' => $this->status,
    );
    if (!empty($this->markup) ) {
      $render_array['webfact_arguments'][2] = array(
        '#type' => 'markup',
        '#markup' => $this->markup,
      );
    }

    return $render_array;
  }
}      // class




