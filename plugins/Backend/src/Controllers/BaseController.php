<?php

namespace Backend\Controllers;

use Nova\Database\ORM\Builder as ModelBuilder;
use Nova\Support\Facades\Auth;
use Nova\Support\Facades\Event;
use Nova\Support\Facades\Request;
use Nova\Support\Facades\View;
use Nova\Support\Arr;

use App\Controllers\BaseController as Controller;
use Backend\Models\Activity;

use Backend\Models\Message;
use Backend\Support\Menu;

class BaseController extends Controller
{
	/**
	 * The currently used Theme.
	 *
	 * @var string
	 */
	protected $theme = 'Backend';

	/**
	 * The currently used Layout.
	 *
	 * @var string
	 */
	protected $layout = 'Default';


	/**
	 * Method executed before any action.
	 *
	 * @return void
	 */
	protected function initialize()
	{
		parent::initialize();

		//
		$request = Request::instance();

		Activity::updateCurrent($request);

		if ($request->ajax() || $request->wantsJson()) {
			return;
		} else if (is_null($user = Auth::user())) {
			return;
		}

		View::share('currentUser', $user);

		// Prepare the SideBar Menu.
		$menu = Menu::make('Backend::Partials/SideBarMenu', function ($menu) use ($user)
		{
			$payload = array($menu, $user);

			Event::fire('backend.menu.sidebar', $payload);
		});

		View::share('sideBarMenu', $menu->render());

		// Prepare the notifications count.
		$notifications = $user->unreadNotifications()->count();

		View::share('notificationCount', $notifications);

		// Prepare the messages count.
		$messages = Message::where('receiver_id', $user->id)->unread()->count();

		View::share('privateMessageCount', $messages);

		// Share the Views the Backend's base URI.
		$segments = $request->segments();

		$path = '';

		if(! empty($segments)) {
			// Make the path equal with the first part if it exists, i.e. 'admin'
			$path = array_shift($segments);

			$segment = ! empty($segments) ? array_shift($segments) : '';

			if (($path == 'admin') && empty($segment)) {
				$path = 'admin/dashboard';
			} else if (! empty($segment)) {
				$path .= '/' .$segment;
			}
		}

		View::share('baseUri', $path);
	}

	/**
	 * Server Side Processor for DataTables.
	 *
	 * @param Nova\Database\Query\Builder|Nova\Database\ORM\Builder $query
	 * @param array $input
	 * @param array $options
	 *
	 * @return array
	 */
	protected function dataTable($query, array $input, array $options)
	{
		$columns = Arr::get($input, 'columns', array());

		// Compute the total count.
		$totalCount = $query->count();

		// Compute the draw.
		$draw = intval(Arr::get($input, 'draw', 0));

		// Handle the global searching.
		$search = trim(Arr::get($input, 'search.value'));

		if (! empty($search)) {
			$query->whereNested(function($query) use($columns, $options, $search)
			{
				foreach($columns as $column) {
					$data = $column['data'];

					$option = Arr::first($options, function ($key, $value) use ($data)
					{
						return ($value['data'] == $data);
					});

					if ($column['searchable'] == 'true') {
						$query->orWhere($option['field'], 'LIKE', '%' .$search .'%');
					}
				}
			});
		}

		// Handle the column searching.
		foreach($columns as $column) {
			$data = $column['data'];

			$option = Arr::first($options, function ($key, $value) use ($data)
			{
				return ($value['data'] == $data);
			});

			$search = trim(Arr::get($column, 'search.value'));

			if (($column['searchable'] == 'true') && (strlen($search) > 0)) {
				$query->where($option['field'], 'LIKE', '%' .$search .'%');
			}
		}

		// Compute the filtered count.
		$filteredCount = $query->count();

		// Handle the column ordering.
		$orders = Arr::get($input, 'order', array());

		foreach ($orders as $order) {
			$index = intval($order['column']);

			$column = Arr::get($input, 'columns.' .$index, array());

			//
			$data = $column['data'];

			$option = Arr::first($options, function ($key, $value) use ($data)
			{
				return ($value['data'] == $data);
			});

			if ($column['orderable'] == 'true') {
				$dir = ($order['dir'] === 'asc') ? 'ASC' : 'DESC';

				$field = $option['field'];

				if ($query instanceof ModelBuilder) {
					$model = $query->getModel();

					$field = $model->getTable() .'.' .$field;
				}

				$query->orderBy($field, $dir);
			}
		}

		// Handle the pagination.
		$start  = Arr::get($input, 'start',  0);
		$length = Arr::get($input, 'length', 25);

		$query->skip($start)->take($length);

		// Retrieve the data from database.
		$results = $query->get();

		//
		// Format the data on respect of DataTables specs.

		$columns = array();

		foreach ($options as $option) {
			$key = $option['data'];

			//
			$field = Arr::get($option, 'field');

			$columns[$key] = Arr::get($option, 'uses', $field);
		}

		//
		$data = array();

		foreach ($results as $result) {
			$record = array();

			foreach ($columns as $key => $value) {
				// Process for standard columns.
				if (is_string($value)) {
					$record[$key] = $result->{$value};

					continue;
				}

				// Process for dynamic columns.
				$record[$key] = call_user_func($value, $result, $key);
			}

			$data[] = $record;
		}

		return array(
			"draw"			=> $draw,
			"recordsTotal"	=> $totalCount,
			"recordsFiltered" => $filteredCount,
			"data"			=> $data
		);
	}
}