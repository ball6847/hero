<?php

/*
* File Upload Fieldtype
*
* @extends Fieldtype
* @class File_upload_fieldtype
*/

class File_upload_fieldtype extends Fieldtype {
	// this property holds the full path to the upload directory
	public $upload_directory;

	/**
	* Constructor
	*
	* Assign basic properties to this fieldtype, useful in listing available fieldtypes.
	* Also defines the MySQL column format for fields of this type.
	*/
	function __construct () {
		parent::__construct();
	 
		$this->compatibility = array('publish','users','products','collections','forms');
		$this->enabled = TRUE;
		$this->fieldtype_name = 'File Upload';
		$this->fieldtype_description = 'Upload a file.';
		$this->validation_error = '';
		$this->db_column = 'VARCHAR(150)';
		
		// configuration
		$this->upload_directory = setting('path_custom_field_uploads');
	}
	
	/**
	* Output Shared
	*
	* Perform actions shared between admin- and frontend-outputs.  Compile attributes of this
	* fieldtype object into an HTML attribute line.
	*
	* @return string $attributes
	*/
	function output_shared () {
		// set defaults
		if ($this->width == FALSE) {
			$this->width = '275px';
		}
		
		// prep classes
		if ($this->required == TRUE) {
			$this->field_class('required');
		}
		
		$this->field_class('file');
		$this->field_class('text');
		
		// add validator names to class list
		foreach ($this->validators as $validator) {
			$this->field_class($validator);
		}
		
		// prep final attributes	
		$attributes = array(
						'type' => 'file',
						'name' => $this->name,
						'style' => 'width: ' . $this->width,
						'class' => implode(' ', $this->field_classes)
						);
		
		// compile attributes
		$attributes = $this->compile_attributes($attributes);
		
		return $attributes;
	}
	
	/**
	* Output Admin
	*
	* Returns the field with it's <label> in an <li> suitable for the admin forms.
	*
	* @return string $return The HTML to be included in a form
	*/
	function output_admin () {
		$attributes = $this->output_shared();
		
		$help = ($this->help == FALSE) ? '' : '<div class="help">' . $this->help . '</div>';
		
		// build HTML
		// we can track an already-uploaded filename with a hidden field so, if we
		// don't have a new upload, we stick with the file we already have
		$return = '<li>
						<label for="' . $this->name . '">' . $this->label . '</label>
						<input type="hidden" name="' . $this->name . '_uploaded" value="' . $this->value . '" />
						<input ' . $attributes . ' /> ' . $this->value . '
						' . $help . '
					</li>';
					
		return $return;
	}
	
	/**
	* Output Frontend
	*
	* Returns the isolated field.  Likely called from the {custom_field} template function.
	*
	* @return string $return The HTML to be included in a form.
	*/
	function output_frontend () {
		$attributes = $this->output_shared();
		
		// build HTML
		$return = '<input type="hidden" name="' . $this->name . '_uploaded" value="' . $this->value . '" />
				   <input ' . $attributes . ' />';
					
		return $return;
	}
	
	/**
	* Validation Rules
	*
	* Return an array of CodeIgniter form_validation rules for this fieldtype.  These are used
	* by form_builder to run a validation across all fields at once using CodeIgniter.
	*
	* @return array $rules
	*/
	function validation_rules () {
		return array();
	}
	
	/**
	* Validate Post
	*
	* This validation is outside of CodeIgniter's form_validation library.  It is run specifically
	* for this field after it passes the major form_validation check.  Not all fieldtypes
	* will require it.  If an error is found, it should be stored in $this->validation_error
	* (using $this->label to refer to the field) and should return FALSE so that the form
	* processor in form_builder knows there was an error.  It will pull the error from
	* $this->validation_error.
	*
	* @return boolean
	*/
	function validate_post () {
		$this->CI->load->helper('file_extension');
		
		if (isset($this->data['filetypes']) and !empty($this->data['filetypes'])) {
			if (in_array('jpg', $this->data['filetypes'])) {
				$this->data['filetypes'][] = 'jpeg';
			}
			elseif (in_array('jpeg', $this->data['filetypes'])) {
				$this->data['filetypes'][] = 'jpg';
			}
		
			if (is_uploaded_file($_FILES[$this->name]['tmp_name']) and !in_array(file_extension($_FILES[$this->name]['name']),$this->data['filetypes'])) {
				$this->validation_error = $this->label . ' is not of the proper filetype.';
			
				return FALSE;
			}
		}
	
		// nothing extra to validate here other than the rulers in $this->validators
		return TRUE;
	}
	
	/**
	* Post to Value
	*
	* Convert the $_POST value to the value that should be inserted into the database.
	*
	* @return string $db_value
	*/
	function post_to_value () {
		if (isset($_FILES[$this->name]) and is_uploaded_file($_FILES[$this->name]['tmp_name'])) {
			$this->CI->settings_model->make_writeable_folder($this->upload_directory,FALSE);
			
			$config = array();
			$config['upload_path'] = $this->upload_directory;
			$config['allowed_types'] = '*';
			$config['encrypt_name'] = TRUE;
			
			// upload class may already be loaded
			if (isset($this->CI->upload)) {
				$this->CI->upload->initialize($config);
			}
			else {
				$this->CI->load->library('upload', $config);
			}
			
			// do upload
			if (!$this->CI->upload->do_upload($this->name)) {
				die(show_error($this->CI->upload->display_errors()));
			}
			
			$filename = $this->CI->upload->file_name;
			
			// reset filename in case we use the uploader again
			$this->CI->upload->file_name = '';
			
			$post_value = str_replace(FCPATH,'',$this->upload_directory . $filename);
		}
		elseif ($this->CI->input->post($this->name . '_uploaded')) {
			$post_value = $this->CI->input->post($this->name . '_uploaded');
		}
		else {
			$post_value = '';
		}
		
		return $post_value;
	}
	
	/**
	* Field Form
	*
	* Build the form that will be used to add/edit fields of this type.
	* 
	* @return string $form Built using form_builder.
	*/
	function field_form ($edit_id = FALSE) {
		// build fieldset with admin_form which is used when editing a field of this type
		$this->CI->load->library('custom_fields/form_builder');
		$this->CI->form_builder->reset();
		
		$filetypes = $this->CI->form_builder->add_field('text');
		$filetypes->label('Allowed Filetypes')
	          ->name('filetypes')
	          ->help('Enter the filetypes (e.g., "jpg", "gif", "pdf", and "doc") that can be uploaded here.  Though not a foolproof mechanism
	          	      for validating filetypes, validating the file extension will help make sure people upload proper files here.  If someone
	          	      does upload a malicious file by renaming the file, the file will still be non-executable as all filenames are encrypted and
	          	      securely stored.');
	          
	    $help = $this->CI->form_builder->add_field('textarea');
	    $help->label('Help Text')
	    	 ->name('help')
	    	 ->width('500px')
	    	 ->height('80px')
	    	 ->help('This help text will be displayed beneath the field.  Use it to guide the user in responding correctly.');
	    	 
	    $required = $this->CI->form_builder->add_field('checkbox');
	    $required->label('Required Field')
	    	  ->name('required')
	    	  ->help('If checked, a file must be uploaded here for the form to be processed.');
	    	  
	    if (!empty($edit_id)) {
	    	$this->CI->load->model('custom_fields_model');
	    	$field = $this->CI->custom_fields_model->get_custom_field($edit_id);
	    	
	    	if (isset($field['data']['filetypes'])) {
	    		$filetypes->value(implode(' ',$field['data']['filetypes']));
	    	}
	    	$help->value($field['help']);
	    	$required->value($field['required']);
	    }	  
	          
		return $this->CI->form_builder->output_admin();      
	}
	
	/**
	* Field Form Process
	*
	* Process the submission of $this->field_form() and return an array of data to be used in custom_fields_model->new_custom_field().
	*
	* Available keys for the returned array: name, type, default (string/array), help, required, validators (array), data (array), 
	*										 options (array), width
	*
	* @return array
	*/
	function field_form_process () {
		// build array for database
		
		// $options will be automatically serialized by the custom_fields_model::new_custom_field() method
		
		return array(
					'name' => $this->CI->input->post('name'),
					'type' => $this->CI->input->post('type'),
					'help' => $this->CI->input->post('help'),
					'required' => ($this->CI->input->post('required')) ? TRUE : FALSE,
					'data' => array('filetypes' => explode(' ', $this->CI->input->post('filetypes')))
				);
	}
}