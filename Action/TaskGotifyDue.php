<?php

namespace Kanboard\Plugin\GotifyDue\Action;

require_once(__DIR__.'/../vendor/autoload.php');

use Kanboard\Model\TaskModel;
use Kanboard\Model\TaskMetadataModel;
use Kanboard\Action\Base;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Gotify a task notification of impending due date 
 */
class TaskGotifyDue extends Base
{
    /**
     * Get automatic action description
     *
     * @access public
     * @return string
     */
    public function getDescription()
    {
        return t('Send Gotify notification of impending task due date');
    }
    /**
     * Get the list of compatible events
     *
     * @access public
     * @return array
     */
    public function getCompatibleEvents()
    {
        return array(
            TaskModel::EVENT_DAILY_CRONJOB,
        );
    }
    /**
     * Get the required parameter for the action (defined by the user)
     *
     * @access public
     * @return array
     */
    public function getActionRequiredParameters()
    {
        return array(
            'subject' => t('Gotify subject'),
            'duration' => t('Hours before due date'),
            'send_to' => array('assignee' => t('Send to Assignee'), 'creator' => t('Send to Creator'), 'both' => t('Send to Both')),
        );
    }
    /**
     * Get the required parameter for the event
     *
     * @access public
     * @return string[]
     */
    public function getEventRequiredParameters()
    {
        return array('tasks');
        
    }
    /**
     * Check if the event data meet the action condition
     *
     * @access public
     * @param  array   $data   Event data dictionary
     * @return bool
     */
    public function hasRequiredCondition(array $data)
    {
        return count($data['tasks']) > 0;
    }

    public function makeDefaultSubject($task)
    {
        $project = $this->projectModel->getById($task['project_id']);

        $remaining = $task['date_due'] - time();
        $days_to_due = 0;
        $hours_to_due = 0;
        $minutes_to_due = 0;
        #$seconds_to_due = 0;

        if ( $remaining > 0 )
        {
        	
            $days_to_due = floor($remaining / 86400);
            $hours_to_due = floor(($remaining % 86400) / 3600);
            $minutes_to_due = floor(($remaining % 3600) / 60);
            #$seconds_to_due = ($rem % 60);
        }

        $time_part = array();

        if ( $days_to_due > 0 )
        {
            $time_part[] = $days_to_due . t(' day') . ($days_to_due == 1 ? '' : t('s'));
        }

        if ( $hours_to_due > 0 )
        {
            $time_part[] = $hours_to_due . t(' hour') . ($hours_to_due == 1 ? '' : t('s '));
        }

        if ( $minutes_to_due > 0 )
        {
            $time_part[] = $minutes_to_due . t(' minute') . ($minutes_to_due == 1 ? '' : t('s '));
        }

        $time_part = implode(t(' and '), $time_part);

        $subject = '[' . $project['name']  . '][' . $task['title']  . '] ' . ($time_part ? t('Due in ') . $time_part : t('Task is due'));
        //print "\n".$subject."\n";

        return $subject;
    }
    
    public function isTimeToGotify($project, $task)
    {
    	// Change $verbose to true while debugging
    	$verbose = false;
    	$verbose_prefix = $verbose ? "isTimeToGotify() - Task \"{$project['name']}::{$task['title']}({$task['id']})\" " : "";
    	
        // Don't send if the task doesn't have a due date
        if ($task['date_due'] == 0) {
            
            $verbose && print "\n{$verbose_prefix}doesn't have a due date; Not time to gotify.";
            
            return false;
        }

        // Don't send if the task is overdue, cause this is handled via notification:overdue-tasks
        if ($task['date_due'] < time()) {
            
            $verbose && print "\n{$verbose_prefix}is overdue; let notification:overdue-tasks do the job.";
            
            return false;
        }
        
        // Don't send if the task itself isn't due soon enough
        $max_duration = $this->getParam('duration') * 3600;
        $duration = $task['date_due'] - time();
        if ($duration >= $max_duration) {
            
            $verbose && print "\n{$verbose_prefix}isn't due soon enough ($duration v. $max_duration); Not time to gotify.";
            
            return false;
        }
        
        // Don't send if we've already sent too recently
        $minimum_gotify_span = 86400;
        $last_gotified = $this->taskMetadataModel->get($task['id'], 'task_last_gotified_toassignee', time() - 86400);
        $last_gotify_span = time() - $last_gotified;
        if ($last_gotify_span < $minimum_gotify_span) {
            
            $verbose && print "\n{$verbose_prefix}has already been gotified about too recently ($last_gotify_span v. $minimum_gotify_span); Not time to gotify.";
            
            return false;
        }
        
        //
        $verbose && print "\n{$verbose_prefix}Sending push notification to Gotify!";
        
        return true;
    }
    
    public function doAction(array $data)
    {
        $results = array();
        
        if ($this->getParam('send_to') !== null) { $send_to = $this->getParam('send_to'); } else { $send_to = 'both'; }
        
        if ($send_to == 'assignee' || $send_to == 'both') {
            
            foreach ($data['tasks'] as $task) {
                
                $project = $this->projectModel->getById($task['project_id']);
                        
                // Only gotify for active projects
                if ( $project['is_active'] ) {
                    
                    // Decide if it's time to send an gotify
                    $is_time_to_send = $this->isTimeToGotify($project, $task);
                    if ($is_time_to_send) {
                        
                        $user = $this->userModel->getById($task['owner_id']);
                            $results[] = $this->sendGotify($task['id'], $user);
                            $this->taskMetadataModel->save($task['id'], ['task_last_gotified_toassignee' => time()]);
                    }
                }
            }
        }
        
        if ($send_to == 'creator' || $send_to == 'both') {
            
            foreach ($data['tasks'] as $task) {
            
                // Only gotify for active projects
                if ( $project['is_active'] ) {
                    
                    // Only gotify is enough time has passed since the last one was sent
                    $is_time_to_send = $this->isTimeToGotify($project, $task);
                    if ( $is_time_to_send ) {
                        
                        $user = $this->userModel->getById($task['creator_id']);
                            $results[] = $this->sendGotify($task['id'], $user);
                            $this->taskMetadataModel->save($task['id'], ['task_last_gotified_tocreator' => time()]);
                    }
                }
            }
        }
        
        return in_array(true, $results, true);
    }
    /**
     * Send Gotify
     *
     * @access private
     * @param  integer $task_id
     * @param  array   $user
     * @return boolean
     */
    private function sendGotify($task_id, array $user)
    {
    	// Change $gotify_verbose to true while debugging gotify
    	$gotify_verbose = false;

        $task = $this->taskFinderModel->getDetails($task_id);
        $subject = $this->getParam('subject') ?: $this->makeDefaultSubject($task);

        // Getting settings from gotify plugin
        $gotify_url = $this->userMetadataModel->get($user['id'], 'gotify_url', $this->configModel->get('gotify_url'));
        $gotify_token = $this->userMetadataModel->get($user['id'], 'gotify_token', $this->configModel->get('gotify_token'));
        $gotify_priority = $this->userMetadataModel->get($user['id'], 'gotify_priority', $this->configModel->get('gotify_priority'));

        $converter = new HtmlConverter();
        $converter->getConfig()->setOption('use_autolinks', false);

        $data = [
            "title"=> "$subject",
            "message"=> $converter->convert($this->template->render('notification/task_create', array('task' => $task))),
            "priority"=> intval($gotify_priority),
            "extras" => [
            "client::display" => [
                "contentType" => "text/markdown"
            ]
            ]
        ];

        $data_string = json_encode($data);

        $url = "$gotify_url/message?token=$gotify_token";

        $headers = [
            "Content-Type: application/json; charset=utf-8"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close ($ch);

        switch ($code) {
    	case "200":
        	if($gotify_verbose){ echo "Your Message was Submitted"; }
        break;
    	case "400":
    		if($gotify_verbose){ echo "Bad Request"; }
        break;
    	case "401":
    		if($gotify_verbose){ echo "Unauthorized Error - Invalid Token"; }
        break;
    	case "403":
    		if($gotify_verbose){ echo "Forbidden"; }
        break;
    	case "404":
    		if($gotify_verbose){ echo "API URL Not Found"; }
        break;
    	default:
    		if($gotify_verbose){ echo "Hmm Something Went Wrong or HTTP Status Code is Missing"; }
		}

        return true;
    }
}
