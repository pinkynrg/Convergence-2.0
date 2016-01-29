<?php namespace App\Http\Controllers;

use App\Http\Controllers\SlackController;
use App\Http\Controllers\EmailsController;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\Company;
use App\Models\Person;
use App\Models\CompanyPerson;
use App\Models\Division;
use App\Models\Status;
use App\Models\Equipment;
use App\Models\Priority;
use App\Models\JobType;
use App\Models\TagTicket;
use App\Models\Tag;
use App\Models\Post;
use Html2Text\Html2Text;
use Request;
use Response;
use Input;
use Form;
use Auth;
use DB;


class TicketsController extends Controller {

	public function index() {
		// if (Auth::user()->can('read-all-ticket')) {
			$data['menu_actions'] = [Form::editItem( route('tickets.create'),"Add new Ticket")];
			$data['active_search'] = true;
			$data['tickets'] = Ticket::where('status_id','!=',TICKET_DRAFT_STATUS_ID)->orderBy('id','desc')->paginate(50);
			// find last updated date and contact info
			foreach ($data['tickets'] as $ticket) {
				$last_post = Post::select('posts.*')->where('ticket_id',$ticket->id)->orderBy('updated_at','desc')->first();
				$which = count($last_post) ? $last_post->updated_at > $ticket->updated_at ? 'post' : 'ticket' : 'ticket';
				$ticket['last_operation_date'] = $which == 'post' ? $last_post->updated_at : $ticket->updated_at;
				$company_person_id = $which == 'post' ? $last_post->author_id : $ticket->creator_id;
				$ticket['last_operation_company_person'] = CompanyPerson::find($company_person_id);
			}
			$data['companies'] = Company::orderBy('name','asc')->get();
			$employees = CompanyPerson::select('company_person.*');
			$employees->leftJoin('people','people.id','=','company_person.person_id');
			$employees->where('company_person.company_id','=',1);
			$employees->orderBy('people.last_name','asc')->orderBy('people.first_name','asc');
			$data['employees'] = $employees->get();
			$data['divisions'] = Division::orderBy('name','asc')->get();
			$data['statuses'] = Status::orderBy('id','asc')->get();

	        $data['title'] = "Tickets";

			return view('tickets/index',$data);
		// }
		// else return redirect()->back()->withErrors(['Access denied to tickets index page']);
	}

	public function create(Request $request) {

		$ticket = Ticket::where('creator_id',Auth::user()->active_contact->id)->where("status_id","9")->first();
		
		if ($ticket) {
			// if there is a started draft redirect to that
			return redirect()->route('tickets.edit',$ticket->id)->with('infos',['This is a draft ticket lastly updated the '.date('m/d/Y H:i:s',strtotime($ticket->updated_at))]);
		}
		else {
			// otherwise redirect to empty form
			$data['companies'] = Company::all();
			$data['priorities'] = Priority::all();
			$data['companies'] = Company::orderBy('name','asc')->get();
			$assignees = CompanyPerson::select('company_person.*');
			$assignees->leftJoin('people','people.id','=','company_person.person_id');
			$assignees->where('company_person.company_id','=',1);
			$assignees->orderBy('people.last_name','asc')->orderBy('people.first_name','asc');
			$data['assignees'] = $assignees->get();
			$data['divisions'] = Division::all();
			$data['job_types'] = JobType::all();

	        $data['title'] = "Create Ticket";

			return view('tickets/create', $data);
		}
	}

	public function store(CreateTicketRequest $request)
	{
		$draft = Ticket::where('creator_id',Auth::user()->active_contact->id)->where("status_id","9")->first();

		$ticket = $draft ? $draft : new Ticket();

		$ticket->title = $request->get('title');
		$ticket->post = $request->get('post');
		$ticket->post_plain_text = Html2Text::convert($request->get('post'));
		$ticket->creator_id = Auth::user()->active_contact->id;
		$ticket->assignee_id = $request->get('assignee_id');
		$ticket->status_id = $request->get('status_id');
		$ticket->priority_id = $request->get('priority_id');
		$ticket->division_id = $request->get('division_id');
		$ticket->equipment_id = $request->get('equipment_id');
		$ticket->company_id = $request->get('company_id');
		$ticket->contact_id = $request->get('contact_id') != 0 ? $request->get('contact_id') : NULL;
		$ticket->job_type_id = $request->get('job_type_id');
		$ticket->emails = $request->get('emails');

		$ticket->save();

       	$this->updateTags($ticket->id,Input::get('tagit'));

       	if (!$draft) { $this->updateHistory($ticket); }

		// EmailsController::sendTicket($ticket->id);
		// SlackController::sendTicket($ticket);

        return (Request::ajax()) ? 'success' : redirect()->route('tickets.index')->with('successes',['Ticket created successfully']);
        return redirect()->route('tickets.index')->with('successes',['Ticket created successfully']);
	}

	public function show($id)
	{
		if (Auth::user()->can('read-ticket')) {
			
			if (Request::ajax()) {
				return Ticket::find($id);
			}
			else {

				$data['menu_actions'] = [Form::editItem( route('tickets.edit', $id),"Edit this ticket"),
										 Form::deleteItem('tickets.destroy', $id, 'Delete this ticket')];
										 
				$data['ticket'] = Ticket::find($id);
				$data['ticket']['posts'] = Post::where('ticket_id',$id)->where('status_id','!=',POST_DRAFT_STATUS_ID)->get();
				$data['ticket']['history'] = TicketHistory::where('ticket_id','=',$id)->orderBy('created_at')->get();
				$data['statuses'] = Status::where('id',WAITING_FOR_FEEDBACK)->orWhere('id',SOLVED)->get();
				$data['draft_post'] = Post::where("ticket_id",$id)->where("status_id",1)->where("author_id",Auth::user()->active_contact->id)->first();
				$data['draft_post']->post = $data['draft_post']->post != "[undefined]" ? $data['draft_post']->post : "";

				switch ($data['ticket']->status_id) {
					case '1' : $data['status_class'] = 'ticket_status_new'; break;
					case '2' : $data['status_class'] = 'ticket_status_new'; break;
					case '3' : $data['status_class'] = 'ticket_status_on_hold'; break;
					case '4' : $data['status_class'] = 'ticket_status_on_hold'; break;
					case '5' : $data['status_class'] = 'ticket_status_on_hold'; break;
					case '6' : $data['status_class'] = 'ticket_status_closed'; break;
					case '7' : $data['status_class'] = 'ticket_status_closed'; break;
				};

			    $data['title'] = "Ticket #".$id;

				return view('tickets/show',$data);
			}
		}
		else return redirect()->back()->withErrors(['Access denied to tickets show page']);	
	}

	public function edit($id)
	{
		$data['ticket'] = Ticket::find($id);
		$data['companies'] = Company::all();
		$data['divisions'] = Division::all();
		$data['job_types'] = JobType::all();
		$data['priorities'] = Priority::all();
		$data['assignees'] = CompanyPerson::where("company_id","=","1")->get();
		$data['tags'] = "";

		foreach ($data['ticket']->tags as $tag) {
			$data['tags'] .= $tag->name.",";
		}

		$is_draft = $data['ticket']->status_id == 9 ? true : false;

		$data['ticket']->title = ($is_draft && $data['ticket']->title == '[undefined]') ? '' : $data['ticket']->title;
		$data['ticket']->post = ($is_draft && $data['ticket']->post_plain_text == '[undefined]') ? '' : $data['ticket']->post;

        $data['title'] = "Edit Ticket #".$id;
        $data['title'] .= $is_draft ? " ~ Draft" : "";

		return view('tickets/edit',$data);
	}

	public function update($id, UpdateTicketRequest $request)
	{
		$ticket = Ticket::find($id);

		$ticket->company_id = $request->get('company_id');
		$ticket->title = $request->get('title');
		$ticket->post = $request->get('post');
		$ticket->post_plain_text = Html2Text::convert($request->get('post'));
		$ticket->assignee_id = $request->get('assignee_id');
		$ticket->status_id = $request->get('status_id');
		$ticket->priority_id = $request->get('priority_id');
		$ticket->division_id = $request->get('division_id');
		$ticket->equipment_id = $request->get('equipment_id');
		$ticket->contact_id = $request->get('contact_id') != 0 ? $request->get('contact_id') : NULL;
		$ticket->job_type_id = $request->get('job_type_id');
		$ticket->emails = $request->get('emails');

		$ticket->save();

       	$this->updateTags($ticket->id,Input::get('tagit'));

       	$this->updateHistory($ticket);

        return redirect()->route('tickets.show',$id)->with('successes',['Ticket updated successfully']);
	}

	private function updateTags($ticket_id, $tags) {

		TagTicket::where('ticket_id',$ticket_id)->forceDelete();

		if ($tags) {

			$tags = explode(",",Input::get('tagit'));

			foreach ($tags as $new_tag) {
				
				$tag = Tag::where('name',$new_tag)->first();
				
				if (!isset($tag->id)) {
					$tag = new Tag;
					$tag->name = $new_tag;
					$tag->save();
				}

				$tag_ticket = new TagTicket;
				$tag_ticket->ticket_id = $ticket_id;
				$tag_ticket->tag_id = $tag->id;
				$tag_ticket->save();
			}
		}
	}

	private function updateHistory($ticket) {

		$history = new TicketHistory;

		$history->changer_id = Auth::user()->active_contact->id;
		$history->ticket_id = $ticket->id;
		$history->title = $ticket->title;
		$history->post = $ticket->post;
		$history->post_plain_text = $ticket->post_plain_text;
		$history->creator_id = $ticket->creator_id;
		$history->assignee_id = $ticket->assignee_id;
		$history->status_id = $ticket->status_id;
		$history->priority_id = $ticket->priority_id;
		$history->division_id = $ticket->division_id;
		$history->equipment_id = $ticket->equipment_id;
		$history->company_id = $ticket->company_id;
		$history->contact_id = $ticket->contact_id;
		$history->job_type_id = $ticket->job_type_id;
		$history->emails = $ticket->emails;
	
		$history->save();
	}

	public function destroy($id)
	{
		echo 'ticket destroy method to be created';
	}

	public function ajaxTicketsRequest($params = "")
    {
    	parse_str($params,$params);

    	$tickets = Ticket::select('tickets.*');
    	$tickets->leftJoin('company_person as creator_contacts','tickets.creator_id','=','creator_contacts.id');
    	$tickets->leftJoin('company_person as assignee_contacts','tickets.assignee_id','=','assignee_contacts.id');
    	$tickets->leftJoin('people as assignees','assignee_contacts.person_id','=','assignees.id');
    	$tickets->leftJoin('people as creators','creator_contacts.person_id','=','creators.id');
    	$tickets->leftJoin('statuses','tickets.status_id','=','statuses.id');
    	$tickets->leftJoin('priorities','tickets.priority_id','=','priorities.id');
    	$tickets->leftJoin('companies','tickets.company_id','=','companies.id');
    	$tickets->leftJoin('divisions','tickets.division_id','=','divisions.id');
    	$tickets->where("tickets.status_id","!=",TICKET_DRAFT_STATUS_ID);
    	// $tickets->leftJoin(DB::raw('(select * from posts where posts.ticket_id = tickets.id order by id desc limit 0,1)'),function ($join) {
    	// 	$join->on('tickets.id','=','posts.ticket_id');
    	// });


    	// apply filters
    	if (isset($params['filters'])) {
    		foreach ($params['filters'] as $key => $filter) {
    			
    			$tickets->where(function($query) use ($filter,$key) {
    				for ($i=0; $i<count($filter); $i++) {
	    				if ($i == 0)
	    					$query->where($key,'=',$filter[$i]);
	    				else
	    					$query->orWhere($key,'=',$filter[$i]);
    				}
    			});
    		}
    	}

    	// apply search
    	if (isset($params['search'])) {
    		$tickets->where('title','like','%'.$params['search'].'%');
    		$tickets->orWhere('tickets.id','=',$params['search']);
    	}

    	// apply ordering
    	if (isset($params['order'])) {
    		$tickets->orderByRaw("case when ".$params['order']['column']." is null then 1 else 0 end asc");
    		$tickets->orderBy($params['order']['column'],$params['order']['type']);
    	}

    	// paginate
   		$tickets = $tickets->paginate(50);

	    $data['tickets'] = $tickets;

	    foreach ($data['tickets'] as $ticket) {
			$last_post = Post::select('posts.*')->where('ticket_id',$ticket->id)->orderBy('updated_at','desc')->first();
			$which = count($last_post) ? $last_post->updated_at > $ticket->updated_at ? 'post' : 'ticket' : 'ticket';
			$ticket['last_operation_date'] = $which == 'post' ? $last_post->updated_at : $ticket->updated_at;
			$company_person_id = $which == 'post' ? $last_post->author_id : $ticket->creator_id;
			$ticket['last_operation_company_person'] = CompanyPerson::find($company_person_id);
		}

        return view('tickets/tickets',$data);
    }

    public function ajaxContactsRequest($id) {
    	$contacts = CompanyPerson::select('company_person.id','people.first_name','people.last_name');
    	$contacts->leftJoin('people','company_person.person_id','=','people.id');
    	$contacts->where('company_id','=',$id);
    	$contacts->orderByRaw("case when people.last_name is null then 1 else 0 end asc");
    	$contacts = $contacts->orderBy('people.last_name','asc');
    	$contacts = $contacts->get();
    	return json_encode($contacts);
    }

    public function ajaxEquipmentRequest($id) {
    	$equipment = Equipment::select('equipment.*')->where('company_id','=',$id);
    	$equipment = $equipment->get();
    	return json_encode($equipment);
    }
}
