CakePHP Scheduler Plugin
========================

Makes scheduling tasks in CakePHP much simpler.

Author
------
Trent Richardson [http://trentrichardson.com]

License
-------
Copyright 2015 Trent Richardson

You may use this project under MIT license.
http://trentrichardson.com/Impromptu/MIT-LICENSE.txt

How It Works
------------
SchedulerShell works by scheduling one cron (SchedulerShell) for your project. Then in bootstrap.php you can create intervals for all your tasks.  Deploying new scheduled tasks are now much easier; one crontab entry executes all your tasks.

Install
-------

This Shell was developed for CakePHP 3.

### Composer Installation (recommended)

In your project's composer.json file add this to your require:

````
"trentrichardson/cakephp-scheduler": "~3.0"
````

### Manual Installation

Copy the Scheduler plugin into your App/Plugin folder and rename the folder to Scheduler.

### Load the Plugin

In your bootstrap.php file add either:

```php
Plugin::loadAll();
```

or

```php
Plugin::load('Scheduler',['autoload'=>true]);
```

Schedule a single system cron by the shortest interval you need for SchedulerShell.php.  For example, if you have 5 tasks and the most often run is every 5 minutes, then schedule this cron to run at least every 5 minutes. For more help see [Shells as Cron Jobs](http://book.cakephp.org/2.0/en/console-and-shells/cron-jobs.html).

Example cron job:

````
*/5 * * * * cd /path/to/app && Console/cake Scheduler.Scheduler
````

This would run the SchedulerShell every 5 minutes.

Now once this shell is scheduled we are able to add our entries to bootstrap.php.  Lets say we want to schedule a CleanUp task daily at 5am and a NewsletterTask for every 15 minutes.

```php
Configure::write('SchedulerShell.jobs', array(
	'CleanUp' => array('interval' => 'next day 5:00', 'task' => 'CleanUp'),// tomorrow at 5am
	'Newsletters' => array('interval' => 'PT15I', 'task' => 'Newsletter') //every 15 minutes
));
```

The key to each entry will be used to store the previous run.  *These must be unique*!

**interval** is set one of two ways.
1) For set times of day we use PHP's [relative time formats](http://www.php.net/manual/en/datetime.formats.relative.php): "next day 5:00".

2) To use an interval to achieve "every 15 minutes" we use [DateInterval](http://www.php.net/manual/en/class.dateinterval.php) string format: "PT15I".

**task** is simply the name of the Task.

There are a couple optional arguments you may pass: "action" and "pass".

**action** defaults to "execute", which is the method name to call in the task you specify.

**pass** defaults to array(), which is the array of arguments to pass to your "action".

```php
Configure::write('SchedulerShell.jobs', array(
	'CleanUp' => array('interval' => 'next day 5:00', 'task' => 'CleanUp', 'action' => 'execute', 'pass' => array()),
	'Newsletters' => array('interval' => 'PT15I', 'task' => 'Newsletter', 'action' => 'execute', 'pass' => array())
));
```

Storage of Results
------------------
SchedulerShell keeps track of each run in a json file.  By default this is stored in TMP and is named "cron_scheduler.json".

If you need to change either of these you may use:

```php
// change the file name
Configure::write('SchedulerShell.storeFile', "scheduler_results.json");
// change the path (note the ending /)
Configure::write('SchedulerShell.storePath', "/path/to/save/");
```

Preventing Simultaneous SchedulerShells Running Same Tasks
----------------------------------------------------------
By default, the SchedulerShell will exit if it is already running and has been for less than 10 minutes. You can adjust this by setting:

```php
// change the number of seconds to wait before running a parallel SchedulerShell; 0 = do not exit
Configure::write('SchedulerShell.processTimeout', 5*60);
```

Other Notes/Known Issues
------------------------
- The optional pass arguments have not been thoroughly tested
- PHP prior to version 5.3.6 only used relative datetime for the DateTime::modify() function. This could result in an interval of "next day 5:00" not running if the previous `lastRun` time was 05:02. Therefore this plugin should only be run on PHP >= 5.3.6.
