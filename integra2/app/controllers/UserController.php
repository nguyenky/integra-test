<?php

use OAuth2\OAuth2;
use OAuth2\Token_Access;
use OAuth2\Exception as OAuth2_Exception;

class UserController extends BaseController
{
	private function getAcl($email)
	{



		$rows = User::select('ip_restriction')->where('email',$email)->first()->toArray();
		if (!empty($rows) && (!empty($rows[0]['ip_restriction'])) && $rows[0]['ip_restriction'] != $_SERVER['REMOTE_ADDR'])
			return [];

		$user = User::with(['group_acls.page' => function ($q)  {
			$q->orderBy('sort','parent');
        }])->where('email',$email)->first()->toArray();

		$data = [];

		foreach ($user['group_acls'] as $key => $value) {
			if($value['page'] !== NULL){
				$item = [
					'url' => $value['page']['url'],
					'title' => $value['page']['title'],
					'parent' => $value['page']['parent'],
					'icon' => $value['page']['icon'],
				];
				array_push($data, $item);	
			}
			
			
		}
	

// 		return DB::select(<<<EOQ
// SELECT url, title, parent, icon
// FROM group_acl ga, users u, pages p
// WHERE ga.group_name = u.group_name
// AND ga.page_url = p.url
// AND u.email = ?
// ORDER BY sort, parent
// EOQ
// 				, [$email]);
		// dd($user);
	return $data;	
	}

	public function login()
	{
		$email = Cookie::get('user');
		if (!empty($email))
		{
			$acl = $this->getAcl($email);
			// dd($acl);
			if (empty($acl)) return View::make('noaccess');
			else
			{
				$acl2 = [];
				foreach ($acl as $a) $acl2[] = $a['url'];
				
				$stores2 = [];

				$stores = OrderAccess::select('store')->whereHas('users',
							function($query) use ($email){
									$query->where('email',$email);
							})->where('visible','1')->get()->toArray();
// 				$stores = DB::select(<<<EOQ
// SELECT store
// FROM integra_prod.order_access oa, integra_prod.users u
// WHERE oa.visible = 1
// AND u.group_name = oa.group_name
// AND u.email = ?
// EOQ
// 						, [$email]);

				foreach ($stores as $s) $stores2[] = $s['store'];
				// dd($acl2);
				return View::make('index', ['acl' => $acl2, 'stores' => $stores2]);
			}
		}

		$provider = OAuth2::provider('google', Config::get('google'));

		if (!isset($_GET['code']))
		{
			return $provider->authorize();
		}
		else
		{
			try
			{
				$params = $provider->access($_GET['code']);
				$token = new Token_Access(['access_token' => $params->access_token]);
				$user = $provider->get_user_info($token);
				$email = $user['email'];

				if (!empty($email))
				{
					Cookie::queue('user', $email, 60 * 24);
					return Redirect::to('/');
				}
			}
			catch (OAuth2_Exception $e)
			{
				return View::make('noaccess');
			}
		}
	}

	public function logout()
	{
		Cookie::queue('user', '', 60 * 24);
		return Redirect::to('/users/login');
	}

	public function nav()
	{
		$email = Cookie::get('user');
		if (empty($email)) return '';
		$acl = $this->getAcl($email);
		return View::make('nav', ['acl' => $acl]);
	}

	public function listUsers()
	{
		$listUser = User::select('id','email','first_name','last_name','ip_restriction','group_name')->get()->toArray();
		//return IntegraUtils::paginate(DB::select('SELECT id, email, first_name, last_name, ip_restriction, group_name FROM integra_prod.users'));
		return IntegraUtils::paginate($listUser);
			
	}

	public function updateUser($id)
	{
		return IntegraUtils::tryFunc(function() use($id)
		{
			// $entry = DB::select('SELECT id FROM integra_prod.users WHERE id = ?', [$id]);

			$entry = User::where('id',$id)->first();

			if (empty($entry))
			{
				// DB::insert('INSERT INTO integra_prod.users (email, first_name, last_name, ip_restriction, group_name) VALUES (?, ?, ?, ?, ?)',
				// 		[
				// 				Input::get('email'),
				// 				Input::get('first_name'),
				// 				Input::get('last_name'),
				// 				Input::get('ip_restriction'),
				// 				Input::get('group_name')
				// 		]);

				User::create([
					'email' => Input::get('email'),
					'first_name' => Input::get('first_name'),
					'last_name' => Input::get('last_name'),
					'ip_restriction' => Input::get('ip_restriction'),
					'group_name' => Input::get('group_name') 
				]);
			}
			else
			{
				// DB::update('UPDATE integra_prod.users SET email = ?, first_name = ?, last_name = ?, ip_restriction = ?, group_name = ? WHERE id = ?',
				// 		[
				// 				Input::get('email'),
				// 				Input::get('first_name'),
				// 				Input::get('last_name'),
				// 				Input::get('ip_restriction'),
				// 				Input::get('group_name'),
				// 				$id
				// 		]);
				$entry->email = Input::get('email');
				$entry->first_name = Input::get('first_name');
				$entry->last_name = Input::get('last_name');
				$entry->ip_restriction = Input::get('ip_restriction');
				$entry->group_name = Input::get('group_name');
				$entry->save();
			}
		},
				"The user was successfully saved.",
				"Check your data.");
	}

	public function destroyUser($id)
	{
		return IntegraUtils::tryFunc(function() use ($id)
		{
			User::where('id',$id)->delete();
			// DB::delete('DELETE FROM integra_prod.users WHERE id = ?', [$id]);
		},
				"The user was successfully deleted.",
				"");
	}

	public function listAcl()
	{
		$groups = [];
		// $rows = DB::select("SELECT DISTINCT group_name FROM integra_prod.users WHERE group_name != 'Admin' ORDER BY 1");
		$rows = User::select('group_name')->where('group_name','<>','Admin')->distinct('group_name')->get()->toArray();
		foreach ($rows as $row) $groups[] = $row['group_name'];

		$pages = [];
		// $rows = DB::select('SELECT title, url FROM integra_prod.pages ORDER BY sort, parent');
		$rows = Page::select('title','url')->orderBy('sort')->orderBy('parent')->get()->toArray();
		foreach ($rows as $row) $pages[] = ['title' => $row['title'], 'url' => $row['url'], 'acl' => []];

		$acl = [];
		// $rows = DB::select("SELECT CONCAT(ga.group_name, '|', p.title) as acl FROM group_acl ga, pages p WHERE ga.page_url = p.url");
		$list = GroupAcl::with('page')->get()->toArray();
		$rows = [];
		foreach ($list as  $value) {
			if($value['page']['title'] !== NULL){
				$string = $value['group_name']."|".$value['page']['title'];	
				$array = [
					'acl' => $string
				];
				array_push($rows, $array);
			}
			
		}

		foreach ($rows as $row) $acl[] = $row['acl'];

		foreach ($pages as &$page)
		{
			foreach ($groups as $group)
			{
				$page['acl'][] = ['group' => $group, 'state' => (in_array($group . '|' . $page['title'], $acl)) ? true : false];
			}
		}

		return ['groups' => $groups, 'pages' => $pages];
	}

	public function updateAcl()
	{
		$state = Input::get('state');

		// grant
		if (!empty($state))
		{
			// DB::insert('INSERT IGNORE INTO group_acl (group_name, page_url) VALUES (?, ?)', [Input::get('group'), Input::get('url')]);
			GroupAcl::create([
				'group_name' => Input::get('group'),
				'page_url' => Input::get('url')
			]);
		}
		// revoke
		else
		{
			// DB::delete('DELETE FROM group_acl WHERE group_name = ? AND page_url = ?', [Input::get('group'), Input::get('url')]);
			GroupAcl::where('group_name',Input::get('group'))
					->where('page_url',Input::get('url'))->delete();
		}

		return '1';
	}
}
