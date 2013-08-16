<?php
/*
    SchedulerShell
    Author: Trent Richardson [http://trentrichardson.com]

    Copyright 2013 Trent Richardson
    You may use this project under MIT license.
    http://trentrichardson.com/Impromptu/MIT-LICENSE.txt

    -------------------------------------------------------------------
    To configure:
    In your bootstrap.php you must add yourjobs:

    Configure::write('SchedulerShell.jobs', array(
        'CleanUp' => array('interval'=>'next day 5:00','task'=>'CleanUp'),// tomorrow at 5am
        'Newsletters' => array('interval'=>'PT15M','task'=>'Newsletter') //every 15 minutes
    ));

    -------------------------------------------------------------------
    Add cake to $PATH:
    - edit .bashrc (linux) or .bash_profile (mac) and add
    - export PATH="/path/to/cakephp/lib/Cake/Console:$PATH"
    - reload with:
        >> source .bashrc

    -------------------------------------------------------------------
    Run a shell task:
    - Cd into app dir
    - run this: 
        >> Console/cake scheduler
    
    -------------------------------------------------------------------
    Troubleshooting
    - may have to run dos2unix to fix line endings in the Config/cake file
*/

class SchedulerShell extends AppShell{
    public $tasks = array();

    /*
        The array of scheduled tasks.
    */
    private $schedule = array();

    /*
        The key which you set Configure::read() for your jobs
    */
    private $configKey = 'SchedulerShell';

    /*
        The path where the store file is placed. null will store in Config folder
    */
    private $storePath = null;

    /*
        The file name of the store
    */
    private $storeFile = 'cron_scheduler.json';


    /*
        The main method which you want to schedule for the most frequent interval
    */
    public function main(){
        $_SERVER['CAKE_ENV'] = (isset($this->args[0]) ? $this->args[0] : 'development');

        // read in the config       
        if($config = Configure::read($this->configKey)){

            if(isset($config['storePath']))
                $this->storePath = $config['storePath'];

            if(isset($config['storeFile']))
                $this->storeFile = $config['storeFile'];

            // read in the jobs from the config
            if(isset($config['jobs'])){
                foreach($config['jobs'] as $k=>$v){
                    $v = array('action'=>'execute', 'pass'=>array()) + $v;
                    $this->connect($k, $v['interval'], $v['task'], $v['action'], $v['pass']);
                }
            }
        }

        // ok, run them when they're ready
        $this->runjobs();
    }

    /*
        The connect method adds tasks to the schedule
        @name string - unique name for this job, isn't bound to anything and doesn't matter what it is
        @interval string - date interval string "PT5M" (every 5 min) or a relative Date string "next day 10:00" 
        @task string - name of the cake task to call
        @action string - name of the method within the task to call
        @pass - array of arguments to pass to the method
    */
    public function connect($name, $interval, $task, $action='execute', $pass=array())
    {
        $this->schedule[$name] = array(
                        'name'=>$name, 
                        'interval'=>$interval, 
                        'task'=>$task,
                        'action'=>$action,
                        'args'=> $pass,
                        'lastRun'=>null,
                        'lastResult'=>''
                    );
    }
    
    /*
        Process the tasks when they need to run
    */
    private function runjobs()
    {
        if(!$this->storePath)
            $this->storePath = TMP;

        // look for a store of the previous run
        $store = "";
        $storeFilePath = $this->storePath.$this->storeFile;
        if(file_exists($storeFilePath))
            $store = file_get_contents($storeFilePath);
        $this->out('Reading from: '. $storeFilePath);

        // build or rebuild the store
        if($store != '')
            $store = json_decode($store,true);
        else $store = $this->schedule;
        
        // run the jobs that need to be run, record the time
        foreach($this->schedule as $name=>$job){
            $now = new DateTime();
            $task = $job['task'];
            $action = $job['action'];

            // if the job has never been run before, create it
            if(!isset($store[$name]))
                $store[$name]=$job;

            // figure out the last run date
            $tmptime = $store[$name]['lastRun'];
            if($tmptime == null)
                $tmptime = new DateTime("1969-01-01 00:00:00");
            elseif(is_array($tmptime))
                $tmptime = new DateTime($tmptime['date'], new DateTimeZone($tmptime['timezone']));
            elseif(is_string($tmptime))
                $tmptime = new DateTime($tmptime);

            // determine the next run time based on the last
            if(substr($job['interval'],0,1)==='P')
                $tmptime->add(new DateInterval($job['interval'])); // "P10DT4H" http://www.php.net/manual/en/class.dateinterval.php
            else $tmptime->modify($job['interval']);    // "next day 10:30" http://www.php.net/manual/en/datetime.formats.relative.php

            // is it time to run? has it never been run before?
            if($tmptime <= $now){
                $this->out("Running $name ---------------------------------------\n");

                if(!isset($this->$task)){
                    $this->$task = $this->Tasks->load($task);

                    // load models if they aren't already
                    foreach($this->$task->uses as $mk=>$mv){
                        if(!isset($this->$task->$mv)){
                            App::uses('AppModel', 'Model');
                            App::uses($mv, 'Model');
                            $this->$task->$mv = new $mv();
                        }
                    }
                }

                // grab the entire schedule record incase it was updated..
                $store[$name] = $this->schedule[$name];

                // execute the task and store the result
                $store[$name]['lastResult'] = call_user_func_array(array($this->$task, $action), $job['args']);

                // assign it the current time
                $now = new DateTime();
                $store[$name]['lastRun'] = $now->format('Y-m-d H:i:s');
            }
        }
        
        // write the store back to the file
        $file = (file_exists($this->storePath.$this->storeFile) ? file_get_contents($this->storePath.$this->storeFile) : '');

        $file = ltrim($file, '[');
        $file = ltrim($file, ',');
        $file = rtrim($file, ']');

        file_put_contents($this->storePath.$this->storeFile, '[' . $file . ',' . json_encode($store) . ']');
    }
}