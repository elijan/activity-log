<?php namespace MePlus\ActivityLog;

/*----------------------------------------------------------------------------------------------------------
	Activity Log
		A simple and clean Laravel 4 activity logger for monitoring
		user activity on a website or web application.

		created by Cody Jassman
		version 0.3.1
		last updated on November 26, 2014
----------------------------------------------------------------------------------------------------------*/

use Illuminate\Database\Eloquent\Model as Eloquent;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

class Activity extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'activity_log';

	/**
	 * Get the user that the activity belongs to.
	 *
	 * @return object
	 */
	public function user()
	{

		return $this->belongsTo(get_class(Config::get('activity-log::authMethod')), 'account_id');
	}


	public function account()
	{

		return $this->belongsTo($this->attributes['account_type'], 'account_id');
	}

	/**
	 * Create an activity log entry.
	 *
	 * @param  mixed
	 * @return boolean
	 */
	public static function log($data = array())
	{
		if (is_object($data)) $data = (array) $data;
		if (is_string($data)) $data = array('action' => $data);

		$activity = new static;

		if (Config::get('activity-log::autoSetUserId')) {
			$user = Config::get('activity-log::authMethod');
			$activity->account_id = isset($user->id)             ? $user->id            : 0;
			$activity->account_type = Config::get('activity-log::authMethod') ? get_class(Config::get('activity-log::authMethod')): 'System';

	         if(Config::get('activity-log::authMethod')) {
                 $activity->account_type = get_class(Config::get('activity-log::authMethod'));
             }else{
                 $activity->account_type = "System";
            }


		}
		if (isset($data['userId'])) {

			$activity->account_id = $data['userId'];
			$activity->account_id = $data['account_type'];
		}

		$activity->content_id   = isset($data['contentId'])   ? $data['contentId']   : 0;
		$activity->content_type = isset($data['contentType']) ? $data['contentType'] : "";
		$activity->action       = isset($data['action'])      ? $data['action']      : "";
		$activity->description  = isset($data['description']) ? $data['description'] : "";
		$activity->details      = isset($data['details'])     ? $data['details']     : "";

		//set action and allow "updated" boolean to replace activity text "Added" or "Created" with "Updated"
		if (isset($data['updated'])) {
			if ($data['updated']) {
				$activity->description = str_replace('Added', 'Updated', str_replace('Created', 'Updated', $activity->description));
				$activity->action = "Updated";
			} else {
				$activity->action = "Created";
			}
		}
		if (isset($data['deleted']) && $data['deleted'])
			$activity->action = "Deleted";

		//set developer flag
		$activity->developer  = !is_null(Session::get('developer')) ? true : false;

		$activity->ip_address = Request::getClientIp();
		$activity->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'No UserAgent';
		$activity->save();

		return true;
	}

	/**
	 * Get the name of the user.
	 *
	 * @return string
	 */
	public function getName()
	{
		if ((bool) $this->developer) {
			return Config::get('activity-log::developerName');
		} else {
			$user = $this->account;

			if (empty($user))
				return "Unknown User";

			if (Config::get('activity-log::usernameAsName')) {
				return $user->username;
			} else {
				if (Config::get('activity-log::fullNameLastNameFirst')) {
					return $user->last_name.', '.$user->first_name;
				} else {
					return $user->first_name.' '.$user->last_name;
				}
			}
		}
	}

	/**
	 * Get a shortened version of the user agent with title text of the full user agent.
	 *
	 * @return string
	 */
	public function getUserAgentPreview()
	{
		return substr($this->user_agent, 0, 42) . (strlen($this->user_agent) > 42 ? '<strong title="'.$this->user_agent.'">...</strong>' : '');
	}

	/**
	 * Get the icon class name for the log entry's action.
	 *
	 * @return string
	 */
	public function getIcon()
	{
		$actionIcons = Config::get('activity-log::actionIcons');
		if (!is_null($this->action) && $this->action == "" || !isset($actionIcons[ucfirst($this->action)]))
			return $actionIcons['X'];

		return $actionIcons[ucfirst($this->action)];
	}

	/**
	 * Get the Glyphicon markup for the log entry's icon.
	 *
	 * @return string
	 */
	public function getIconMarkup()
	{
		return '<span class="glyphicon glyphicon-'.$this->getIcon().'" title="'.$this->action.'"></span>';
	}


	public function getObject() {

		if(class_exists($this->content_type)){

			$class = new $this->content_type();

			$activity = $class->find($this->content_id);
			if(method_exists($activity, 'getActivityName')) {
				return $class->find($this->content_id)->getActivityName();
			}

		}
	}

}