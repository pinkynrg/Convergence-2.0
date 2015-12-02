<?php namespace App\Http\Requests;

use App\Http\Requests\Request;

class CreateTicketRequest extends Request {

	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'title' => 'required|string',
			'post' => 'required|string',
			'creator_id' => 'required|integer',  
			'assignee_id' => 'required|integer',  
			'status_id' => 'required|integer',  
			'priority_id' => 'required|integer',  
			'division_id' => 'required|integer',  
			'equipment_id' => 'integer',  
			'company_id' => 'required|integer',  
			'contact_id' => 'integer',  
			'job_type_id' => 'required|integer'
		];
	}

}
