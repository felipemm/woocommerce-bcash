<?php
/*
 Plugin Name: WooCommerce Pagamento Digital
 Plugin URI: http://felipematos.com/loja
 Description: Adiciona o gateway de pagamento do Pagamento Digital no WooCommerce
 Version: 1.0
 Author: Felipe Matos <chucky_ath@yahoo.com.br>
 Author URI: http://felipematos.com
 License: GPLv2
 Requires at least: 3.3
 Tested up to: 3.4.1
 */

//hook to include the payment gateway function
add_action('plugins_loaded', 'gateway_pagdigital', 0);

//hook function
function gateway_pagdigital(){
	
	//classe de verificação do retorno de pagamento
	class pagdigitalNpi {
		
		private $timeout = 20; // Timeout em segundos
		private $tokenid = ''; //Token do Pagamento Digital
		private $npi_url = ''; //Url do NPI do Pagamento Digital
		
		public function setTokenID($token){
			$this->tokenid = $token;
		}
		
		public function setNpiUrl($url){
			$this->npi_url = $url;
		}
		
		public function notificationPost() {
			$postdata = 'token='.$this->tokenid;
			foreach ($_POST as $key => $value) {
				$valued    = $this->clearStr($value);
				$postdata .= "&$key=$valued";
			}
			return $this->verify($postdata);
		}
		
		private function clearStr($str) {
			if (!get_magic_quotes_gpc()) {
				$str = addslashes($str);
			}
			return $str;
		}
		
		private function verify($data) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $this->npi_url);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			$result = trim(curl_exec($curl));
			curl_close($curl);
			return $result;
		}
		
	}
	
	
	//Pagamento Digital payment gateway class
	class woocommerce_pagdigital extends woocommerce_payment_gateway {
		
		public function __construct() { 
			global $woocommerce;
			
			$this->id      	     = 'pagdigital';
			$this->icon     	 = apply_filters('woocommerce_pagdigital_icon', $url = plugins_url('woocommerce-pagdigital/pagdigital.png'));
			$this->has_fields    = false;
			$this->npi_url       = 'https://www.pagamentodigital.com.br/checkout/verify/';
			$this->checkout_url  = 'https://www.pagamentodigital.com.br/checkout/pay/';
			
			// Load the form fields.
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
			// Define user set variables
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->email = $this->settings['email'];
			$this->tokenid = $this->settings['tokenid']; 
			$this->debug  = $this->settings['debug'];    
			
			// Logs
			if ($this->debug=='yes') $this->log = $woocommerce->logger();
			
			// Actions
			add_action( 'init', array(&$this, 'check_ipn_response') );
			add_action('valid-pagdigital-standard-ipn-request', array(&$this, 'successful_request') );
			add_action('woocommerce_receipt_pagdigital', array(&$this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
			
			if ( !$this->is_valid_for_use() ) $this->enabled = false;
		} 
		
		//Check if this gateway is enabled and available in the user's country
		function is_valid_for_use() {
			if (!in_array(get_option('woocommerce_currency'), array('AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP'))) return false;
			return true;
		}
		
		//Initialise Gateway Settings Form Fields
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Habilita/Desabilita', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Habilita ou não Pagamento Digital.', 'woothemes' ),
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Título', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Título a ser exibido para o consumidor durante o checkout.', 'woothemes' ),
					'default' => __( 'Pague com Pagamento Digital', 'woothemes' )
				),
				'description' => array(
					  'title' => __( 'Mensagem', 'woothemes' ),
					  'type' => 'textarea',
					  'description' => __( 'Permite exibir uma mensagem para o consumidor quando ele selecionar esta forma de pagamento.', 'woothemes' ),
					  'default' => 'O Pagamento Digital é o meio de pagamento mais completo e eficiente na proteção contra fraudes em compras online.'
				  ),
				'email' => array(
					'title' => __( 'E-Mail', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'E-mail da conta do Pagamento Digital que receberá o pagametno.', 'woothemes' )
				),
				'tokenid' => array(
					'title' => __( 'Token', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Token gerado pelo Pagamento Digital para pagamento via API', 'woothemes' )
				),
				'debug' => array(
					'title' => __( 'Debug', 'woothemes' ), 
					'type' => 'checkbox', 
					'label' => __( 'Habilita geração de log para debug (<code>woocommerce/logs/pagdigital.txt</code>)', 'woothemes' ), 
					'default' => 'yes'
				)
			);
		} // End init_form_fields()
		
		//Admin Panel Options
		//Options for bits like 'title' and availability on a country-by-country basis
		public function admin_options() {
			?>
			<h3><?php _e('Pagamento Digital', 'woothemes'); ?></h3>
			<p><?php _e('Opção para pagamento através do Pagamento Digital', 'woothemes'); ?></p>
			<table class="form-table">
				<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
				?>
			</table><!--/.form-table-->
			<?php
		} // End admin_options()

		// There are no payment fields for pagdigital, but we want to show the description if set.
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}

		//generate the form to send to Pagamento Digital
		function generate_pagdigital_form($order_id){
			global $woocommerce;
			$order = &new woocommerce_order( $order_id );
			
			$pagdigital_url = $this->checkout_url;
			
			//create array used to store order data for the payment request
			$pagdigital_args = array();
			//create array used to store order data for the payment request
			$pagdigital_args = array();
			$pagdigital_args['email_loja']  = $this->email;
			$pagdigital_args['tipo_integracao']  = 'PAD';
			$pagdigital_args['id_pedido']  = $order_id;
			
			//buyer information
			$pagdigital_args['nome']  = $order->billing_first_name . " " . $order->billing_last_name;
			$pagdigital_args['telefone']  = $order->billing_phone;
			$pagdigital_args['email']  = $order->billing_email;
			$pagdigital_args['cep']  = $order->postcode;
			
			//url de retorno/notificação    
			$pagdigital_args['url_retorno']  = htmlspecialchars($this->get_return_url($order));
			$pagdigital_args['url_aviso']  = 'http://felipematos.com/loja/finalizar-compra/pedido-recebido/'; //htmlspecialchars($this->get_return_url($order));
			$pagdigital_args['redirect']  = 'true';
			$pagdigital_args['redirect_time']  = '5';
			
			
			//order information
			$item_loop = 0;
			if (sizeof($order->get_items())>0){
				foreach ($order->get_items() as $item){
					if ($item['qty']){
						$item_loop++;
						
						$product = $order->get_product_from_item($item);
						
						$item_name   = $item['name'];
						
						$item_meta = new order_item_meta( $item['item_meta'] );          
						if ($meta = $item_meta->display( true, true )) :
							$item_name .= ' ('.$meta.')';
						endif;
						
						$pagdigital_args['produto_codigo_'.$item_loop]     = $product->get_sku();
						$pagdigital_args['produto_descricao_'.$item_loop]  = $item_name;
						$pagdigital_args['produto_valor_'.$item_loop]      = number_format($order->get_item_total( $item, false ),2,".","");
						$pagdigital_args['produto_qtde_'.$item_loop]       = $item['qty'];
					}
				}
			}
			$pagdigital_args['frete'] = number_format($order->get_shipping(),2,".","");
			$pagdigital_args['desconto'] = number_format($order->get_total_discount(),2,".","");
			
			
			$pagdigital_args_array = array();
			
			foreach ($pagdigital_args as $key => $value) {
				$pagdigital_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}
			
			
			$woocommerce->add_inline_js('
				jQuery("body").block({ 
				message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Obrigado pela compra. Estamos transferindo para o Pagamento Digital para realizar o pagamento.', 'woothemes').'", 
					overlayCSS: 
					{ 
						background: "#fff", 
						opacity: 0.6 
					},
						css: { 
						padding:        20, 
						textAlign:      "center", 
						color:          "#555", 
						border:         "3px solid #aaa", 
						backgroundColor:"#fff", 
						cursor:         "wait",
						lineHeight:    "32px"
					} 
				});
				jQuery("#submit_pagdigital_payment_form").click();
			');
			
			
			
			$payment_form = '<form action="'.esc_url( $pagdigital_url ).'" method="post" id="pagdigital_payment_form">
							' . implode('', $pagdigital_args_array) . '
							<input type="submit" class="button" id="submit_pagdigital_payment_form" value="'.__('Pague com Pagamento Digital', 'woothemes').'" /> 
							<a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancelar pedido', 'woothemes').'</a>
							</form>';
			//    ' . $this->get_return_url( $order ) . '
			
			if ($this->debug=='yes') $this->log->add( 'pagdigital', "Pedido gerado com sucesso. Abaixo código HTML do formulário:");
			if ($this->debug=='yes') $this->log->add( 'pagdigital', $payment_form);
			
			return $payment_form;
		}
		
		// Process the payment and return the result
		function process_payment( $order_id ) {
			
			$order = &new woocommerce_order( $order_id );
			
			return array(
				'result'   => 'success',
				'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			);
			
		}    
		
		// receipt_page
		function receipt_page( $order ) {
			
			echo '<p>'.__('Obrigado pelo seu pedido. Por favor clique em "Pagar com Pagamento Digital" para finalizar sua compra.', 'woothemes').'</p>';
			
			echo $this->generate_pagdigital_form( $order );
		}

		
		// Check Pagamento Digital IPN validity
		function check_ipn_request_is_valid() {
			global $woocommerce;
			
			$pagdigital_url = $this->npi_url;
			
			if ($this->debug=='yes') $this->log->add( 'pagdigital', 'NPI URL: '. $pagdigital_url);
			
			if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Verificando se a resposta do NPI e valida' );
			
			if (count($_POST) > 0) {
				
				// POST recebido, indica que é a requisição do NPI.
				if ($this->debug=='yes') $this->log->add( 'pagdigital', 'POST recebido, indica que e a requisicao do NPI.');
				
				//$npi = new pagdigitalNpi();
				//$npi->setTokenID($this->tokenid);
				//$npi->setNpiUrl($pagdigital_url);
				//$result = $npi->notificationPost();
				$id_transacao = $_POST['id_transacao'];
				$data_transacao = $_POST['data_transacao'];
				$data_credito = $_POST['data_credito'];
				$valor_original = $_POST['valor_original'];
				$valor_loja = $_POST['valor_loja'];
				$valor_total = $_POST['valor_total'];
				$desconto = $_POST['desconto'];
				$acrescimo = $_POST['acrescimo'];
				$tipo_pagamento = $_POST['tipo_pagamento'];
				$parcelas = $_POST['parcelas'];
				$cliente_nome = $_POST['cliente_nome'];
				$cliente_email = $_POST['cliente_email'];
				$cliente_rg = $_POST['cliente_rg'];
				$cliente_data_emissao_rg = $_POST['cliente_data_emissao_rg'];
				$cliente_orgao_emissor_rg = $_POST['cliente_orgao_emissor_rg'];
				$cliente_estado_emissor_rg = $_POST['cliente_estado_emissor_rg'];
				$cliente_cpf = $_POST['cliente_cpf'];
				$cliente_sexo = $_POST['cliente_sexo'];
				$cliente_data_nascimento = $_POST['cliente_data_nascimento'];
				$cliente_endereco = $_POST['cliente_endereco'];
				$cliente_complemento = $_POST['cliente_complemento'];
				$status = $_POST['status'];
				$cod_status = $_POST['cod_status'];
				$cliente_bairro = $_POST['cliente_bairro'];
				$cliente_cidade = $_POST['cliente_cidade'];
				$cliente_estado = $_POST['cliente_estado'];
				$cliente_cep = $_POST['cliente_cep'];
				$frete = $_POST['frete'];
				$tipo_frete = $_POST['tipo_frete'];
				$informacoes_loja = $_POST['informacoes_loja'];
				$id_pedido = $_POST['id_pedido'];
				$free = $_POST['free'];
				$post = "transacao=".$id_transacao."&status=".$status."&cod_status=".$cod_status."
						&valor_original=".$valor_original."&valor_loja=".$valor_loja."&token=".$this->tokenid;
				$enderecoPost = "https://www.pagamentodigital.com.br/checkout/verify/";
				
				
				if ($this->debug=='yes') $this->log->add( 'pagdigital', 'POST de Verificação = '. $post);
				
				ob_start();
				$ch = curl_init();
				curl_setopt ($ch, CURLOPT_URL, $enderecoPost);
				curl_setopt ($ch, CURLOPT_POST, 1);
				curl_setopt ($ch, CURLOPT_POSTFIELDS, $post);
				curl_exec ($ch);
				$resposta = ob_get_contents();
				ob_end_clean();
				
				
				
				$transacaoID = isset($_POST['id_transacao']) ? $_POST['id_transacao'] : '';
				
				if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Numero da Transação = '. $transacaoID);
				
				if(trim(strtoupper($resposta))=="VERIFICADO"){
					if ($this->debug=='yes') $this->log->add( 'pagdigital', 'POST Validado pelo Pagamento Digital');
					return true;
					//O post foi validado pelo Pagamento Digital.
				} else if ($result == "FALSO") {
					//O post não foi validado pelo Pagamento Digital.
				} else {
					//Erro na integração com o Pagamento Digital.
				}
				
			} else {
				// POST não recebido, indica que a requisição é o retorno do Checkout Pagamento Digital.
				// No término do checkout o usuário é redirecionado para este bloco.
			}
			
		}
		
		// Check for pagdigital IPN Response
		function check_ipn_response() {
			if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Checando resposta. Verificando POST...'.$_POST['id_transacao']);
			
			if ( !empty($_POST['id_pedido']) && !empty($_POST['id_transacao']) ) {
				
				$_POST = stripslashes_deep($_POST);
				if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Checando se o POST é válido');
				
				if ($this->check_ipn_request_is_valid()){
					if ($this->debug=='yes') $this->log->add( 'pagdigital', 'POST válido. Atualizando pedido.');
					do_action("valid-pagdigital-standard-ipn-request", $_POST);
					
				}
				
			}
			
		}
		
		//======================================================================
		// Successful Payment!
		//======================================================================
		function successful_request( $posted ) {
			if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Pedido = '.$posted['id_pedido'].' / Status = '.$posted['cod_status'].'-'.$posted['status']);
			
			if ( !empty($posted['id_transacao']) && !empty($posted['id_pedido']) ) {
				$order = new woocommerce_order( (int) $posted['id_pedido'] );

				// Check order not already completed
				if ($order->status == 'completed' && (int)$posted['cod_status'] == 4) {
					if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Pedido '.$posted['id_pedido'].' já se encontra completado no sistema!');
					exit;
				}

				// We are here so lets check status and do actions
				switch ((int)$posted['cod_status']){
					case 0: //em andamento

						$order->add_order_note( __('Aguardado confirmação de pagamento.', 'woothemes') );
						if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Pedido '.$posted['id_pedido'].': Aguardado confirmação de pagamento.');
						break;
                        
                    case 1: //aprovada
						
                        // Payment completed
						$order->add_order_note( __('Pagamento aprovado pelo Pagamento Digital.', 'woothemes') );
						$order->payment_complete();
						// Store Pagamento Digital Details
						update_post_meta( (int) $posted['id_pedido'], 'E-Mail', $posted['email']);
						update_post_meta( (int) $posted['id_pedido'], 'Código Transação', $posted['id_transacao']);
						update_post_meta( (int) $posted['id_pedido'], 'Método Pagamento', $posted['cod_pagamento'].'-'.$posted['meio_pagamento']);
						update_post_meta( (int) $posted['id_pedido'], 'Data Transação', date("F j, Y, g:i a")); 
						if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Pedido '.$posted['id_pedido'].': Pagamento aprovado pelo Pagamento Digital.');
						break;

                    case 2: //cancelada
						
						$order->update_status('cancelled','Pagamento foi cancelado pelo Pagamento Digital.');
						if ($this->debug=='yes') $this->log->add( 'pagdigital', 'Pedido '.$posted['id_pedido'].': Pagamento foi cancelado pelo Pagamento Digital.');
						break;
						
					default:
						// No action
						break;
				}
			}
		} //End of successful_request()
	}

	//Add the gateway to WooCommerce
	function add_pagdigital_gateway( $methods ) {
		$methods[] = 'woocommerce_pagdigital'; return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'add_pagdigital_gateway' );
}
?>