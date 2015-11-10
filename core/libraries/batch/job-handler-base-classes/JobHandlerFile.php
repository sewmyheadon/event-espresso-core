<?php 
namespace EventEspresso\Core\Libraries\Batch\JobHandlerBaseClass;
use EventEspresso\Core\Libraries\Batch\Helpers\JobHandlerException;
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

abstract class JobHandlerFile extends JobHandler {
	/**
	 *
	 * @var EEHI_File
	 */
	protected $_file_helper = null;
	const temp_folder_name = 'batch_temp_folder';
	public function __construct( EEHI_File $file_helper = null ) {
		if( ! $file_helper ) {
			$this->_file_helper = new \EEH_File();
		}
	}
	/**
	 * Creates a file
	 * @param string $job_id
	 * @param string $filename
	 * @return filepath
	 */
	public function create_file_from_job_with_name( $job_id, $filename ) {
		try{
			$success = $this->_file_helper->ensure_folder_exists_and_is_writable( EVENT_ESPRESSO_UPLOAD_DIR . JobHandlerFile::temp_folder_name );
			if( $success ) {
				$success = $this->_file_helper->ensure_folder_exists_and_is_writable( EVENT_ESPRESSO_UPLOAD_DIR . JobHandlerFile::temp_folder_name . DS . $job_id );
			}
			if( $success ) {
				$filepath = EVENT_ESPRESSO_UPLOAD_DIR . JobHandlerFile::temp_folder_name . DS . $job_id . DS. $filename;
				$success = $this->_file_helper->ensure_file_exists_and_is_writable( $filepath );
			}
			//those methods normally fail with an exception, but if not, let's do it
			if( ! $success ) {
				throw new \EE_Error( 'could_not_create_temp_file', 
				__( 'An unknown error occurred', 'event_espresso' ));
			}
		}catch( \EE_Error $e ) {
			throw new JobHandlerException( 
					'could_not_create_temp_file', 
					sprintf( 
							__( 'Could not create temporary file for job %1$s, because: %2$s ', 'event_espresso' ),
							$job_id,
							$e->getMessage() ),
					$e );
		}
		return $filepath;
	}
	
	/**
	 * Gets the URL to download the file
	 * @param string $filepath
	 * @return string url to file
	 */
	public function get_url_to_file( $filepath ) {
		str_replace( EVENT_ESPRESSO_UPLOAD_DIR, EVENT_ESPRESSO_UPLOAD_URL, $filepath );
	}
}
