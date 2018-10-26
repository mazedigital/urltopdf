<?php

	Class Extension_URLtoPDF extends Extension {

		public function about() {
			return array(
				'name' => 'URL to PDF',
				'version' => '0.1',
				'release-date' => '2011-07-13',
				'author' => array(
					'name' => 'Brendan Abbott',
					'website' => 'http://www.bloodbone.ws',
					'email' => 'brendan@bloodbone.ws'
				),
				'description' => 'Uses the mPDF library to take your HTML page and output it as a PDF'
			);
		}

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendOutputPostGenerate',
					'callback' => 'generatePDFfromURL'
				),
			);
		}

	/*-------------------------------------------------------------------------
		Delegates:
	-------------------------------------------------------------------------*/

		/**
		 * Generate a PDF from a complete URL
		 */
		public function generatePDFfromURL(array &$context = null) {
			// return;
			$page_data = Frontend::Page()->pageData();

			if(!isset($page_data['type']) || !is_array($page_data['type']) || empty($page_data['type'])) return;

			if (isset($_GET['debug']) || isset($_GET['preview']) ) return;

			$matches = array();
			$hasAttachments = preg_match("~^(<!.*>\s)?(<attachments>.+<\/attachments>)~sU", $context['output'],$matches);

			$attachments = array();
			if ($hasAttachments){
				$attachmentsString = $matches[2];

				$context['output'] = str_replace($attachmentsString, "", $context['output']);

				$attachmentsXML = XMLElement::convertFromXMLString('attachments',$attachmentsString);
				
				foreach ($attachmentsXML->getChildren() as $key => $value) {
					$attachments[] = $value->getValue();
				}

			}

			
			$chartMatches = array();
			$hasCharts = preg_match_all("~<chart>(.|\n)*?<\/chart>~", $context['output'],$chartMatches);
	
			if ($hasCharts){
				
				foreach($chartMatches[0] as $key=>$value) {

					$value = str_replace('<chart>', '', $value);
					$value = str_replace('</chart>', '', $value);				


					// POST QUERY TO GET IMAGE
					$url = 'https://abzu55zvt4.execute-api.eu-west-2.amazonaws.com/Develop';

					$data_string = $value;

				
					$ch = curl_init();

				
					curl_setopt($ch,CURLOPT_URL, $url);

					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
					curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
					curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
					    'Content-Type: application/json',                                                                                
					    'Content-Length: ' . strlen($data_string))                                                                       
					);                                         

					//execute post
					$result = json_decode(curl_exec($ch));
				
					//close connection
					curl_close($ch);

					if(isset($result->data)){

						$img = 'data:image/png;base64, ' . $result->data;

						$context['output'] = str_replace($value, "<img src='{$img}'/>", $context['output']);

					}	
					else {

						$context['output'] = str_replace($value, "", $context['output']);

					}

						// var_dump($context['output']);die;


				} 
			}

			foreach($page_data['type'] as $type) {
				if($type == 'pdf') {
					// Page has the 'pdf' type set, so lets generate!
					$this->generatePDF($context['output'],$attachments);
				}
				else if($type == 'pdf-attachment') {
					// Page has the 'pdf-attachment' type set, so lets generate some attachments
					$this->generatePDFAttachments($context['output'],$attachments);
				}
			}
		}

		public function generatePDF($output,$attachments) {
			$params = Frontend::Page()->_param;

			$pdf = self::initPDF();

			$pdf->SetAuthor($params['website-name']);
			$pdf->SetTitle($params['page-title']);

			// output the HTML content
			$pdf->writeHTML($output);

			// reset pointer to the last page
			// $pdf->lastPage();

			// If attachments are available add these to the PDF
			if ($attachments){
				$pdf->SetImportUse();

				$filesTotal = count($attachments);

				foreach ($attachments as $fileNumber => $attachment) {

					$filepath = WORKSPACE . $attachment;

					$pagecount = $pdf->SetSourceFile($filepath);

				    for ($i=1; $i<=$pagecount; $i++) {
				    	//add a new page as PDF should not contain one
				        $pdf->AddPage();

				        $import_page = $pdf->ImportPage();
				        $pdf->UseTemplate($import_page);
				    }
				}
			}


			//Close and output PDF document
			if ($params['current-page']=='pdf'){
				$name = $params['root-page'];
			} else {
				$name = $params['current-page'];
			}
			$name .= '-'.$params['member-current-account.name'].'.pdf';
			$pdf->Output('name','I');
			exit();
		}

		public function generatePDFAttachments(&$output,$attachments) {
			$params = Frontend::Page()->_param;

			$dom = new DOMDocument('1.0', 'UTF-8');
			$doc->formatOutput = true;
			$dom->loadHTML($output);

			if($dom === false) return $output;

			$xpath = new DOMXPath($dom);

			// Copy any <link rel='stylesheet'/> or <style type='text/css'> prepend to the blocks
			$css = '';
			$styling = $xpath->query('//link[@rel="stylesheet"] | //style[@type="text/css"]');
			if($styling->length !== 0) foreach($styling as $style) {
				$css .= $dom->saveXML($style);
			}

			// Find anything with @data-utp attribute set to attachment
			$blocks = $xpath->query('//*[@data-utp = "attachment"]');
			if($blocks->length !== 0) foreach($blocks as $block) {
				// Get the content in those blocks
				$data = $dom->saveXML($block);

				// Send the block to the PDF generator, saving it in /TMP
				$data = $css . $data;
				$pdf = self::initPDF();

				// output the HTML content
				$pdf->writeHTML($data, true, false, true, false, '');

				// reset pointer to the last page
				// $pdf->lastPage();

				// get the output of the PDF as a string and save it to a file
				// attempt to find the filename if it's provided with @data-utp-filename
				if(!$filename = $xpath->evaluate('string(//@data-utp-filename)')) {
					 $filename = md5(sprintf('%s - %s', $params['website-name'], $params['page-title']));
				}
				$filename = TMP . '/' . Lang::createFilename($filename) . '.pdf';

				General::writeFile($filename, $pdf->Output($filename, 'S'), Symphony::Configuration()->get('write_mode', 'file'));

				// Replace the attachment node with <link rel='attachment' href='{path/to/file}' />
				$link = $dom->createElement('link');
				$link->setAttribute('rel', 'attachment');
				$link->setAttribute('href', str_replace(DOCROOT, URL, $filename));

				$block->parentNode->replaceChild($link, $block);
			}

			$output = $dom->saveHTML();
		}


		private static function initPDF() {
			require_once EXTENSIONS . '/urltopdf/vendor/autoload.php';

			define('_MPDF_TEMP_PATH',TMP.'/'); 
			define('_JPGRAPH_PATH',TMP. '/graph/');
			// define('_MPDF_TTFONTPATH',WORKSPACE . '/urltopdf/ttfonts/'); 
			// define('_MPDF_TTFONTDATAPATH',WORKSPACE . '/urltopdf/ttfontdata/'); 
			$pdf = new mPDF('', 'A4',0,'',15,15,25,25,0,16,'P');
			// require_once(EXTENSIONS . '/urltopdf/lib/MPDF57/mpdf.php');

			// $pdf = new mpdf('', 'A4',0,'',15,15,25,25,0,16,'P'); //left,right,top,bottom
			$pdf->simpleTables = true;

			$pdf->h2toc = array('H1'=>0, 'H2'=>1, 'H3'=>2);


			$pdf->debug = true;
			//$pdf->packTableData = true;
			$securePassword = uniqid('securePassword');
			$pdf->SetProtection(array('copy','print'), '', $securePassword);
			//$pdf->setAutoTopMargin  = 'stretch';
			//$pdf->setAutoBottomMargin = 'stretch';

			return $pdf;
		}

	}
