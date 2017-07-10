<?php include "config.php"; ?>
<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" type="image/png" href="http://<?php echo $url; ?>/favicon.png">
    <title>Serviço SPFBL</title>
    <link rel="stylesheet" href="http://<?php echo $url; ?>/style.css">
	<link rel="stylesheet" href="/notify.css">
  </head>
  <body>
    <div id="container">
      <div id="divlogo">
        <img src="http://<?php echo $url; ?>/logo.png" alt="Logo">
      <hr>
      </div>
      <div id="divmsg">
        <p id="titulo">Verificação de configuração.</p>
      </div>
		<hr>
<?php

// Nossas variáveis
$ip = filter_var(urldecode($_GET['ip']), FILTER_VALIDATE_IP);
$remetente = filter_var(urldecode($_GET['rem']),FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);
$helo = $_GET['helo'];
$destinatario = filter_var(urldecode($_GET['dest']),FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);

if(!empty($ip) && !empty($remetente) && !empty($helo) && !empty($destinatario)){
	iniciar($ip, $remetente, $helo, $destinatario);
}else{
	echo "<div class='warn'> Sem informações para processar. </div>";
}

function iniciar($ip, $remetente, $helo, $destinatario){
	//Teste Falha - Sem SPF
	/*
	$ip = filter_var('168.90.189.201', FILTER_VALIDATE_IP);
	$remetente = filter_var('parceiro@ftp.qualidadenacompra.com.br',FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);
	$helo = 'smtp1.poucosprecos.com.br';
	$destinatario = filter_var('vendas2@zbn.com.br',FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);
	*/

	// Teste OK
	/*
	$ip = filter_var('189.126.112.199', FILTER_VALIDATE_IP);
	$remetente = filter_var('faturamento@tosel.com.br',FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);
	$helo = 'hm1480-n-199.locaweb.com.br';
	$destinatario = filter_var('nfe2@bumi.com.br',FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);
	*/

	// Teste WHITE
	/*
	$ip = filter_var('200.144.6.50', FILTER_VALIDATE_IP);
	$remetente = filter_var('bec@sp.gov.br',FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);
	$helo = 'websmtp.redegov.sp.gov.br';
	$destinatario = filter_var('contabil.noroeste@ensite.com.br',FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);
	*/

	// Teste com erros de reverso
	//$ip = filter_var('201.49.73.21', FILTER_VALIDATE_IP);
	//$ip = filter_var('136.36.189.201', FILTER_VALIDATE_IP);

	// Separa usuario de dominio para usarmos como variavel de testes
	list($user, $domain) = explode('@', $remetente);

	$reverse_ip = inverteIP($ip);

	echo "<div class='spfbl'> Testes com dados recebidos\n";
	// Verifica MX
	$mx = mxrecordValidate($domain);

	// Verifica SPF
	check_spf_ip($domain, $ip);

	// Verifica Reverso
	$reverso = check_reverse_ip($reverse_ip, $helo, $mx);

	// Descobre o IP do MX
	$ipmx = gethostbyname($mx);

	// Verifica se MX e SMTP do remetente são o mesmo server.
	check_servers($ip, $ipmx);
	echo "</div>\n";

	echo "<hr>";
	// Consulta SPFBL
	spfbl($ip, $remetente, $helo, $destinatario);

	echo "<hr>";

	echo "<div class='spfbl'> Outras informações\n";
	// Lista dados
	echo "<div class='subdiv'>";
	echo "<div class='subdiv'> Informações do seu domínio <span>".$domain."</span></div>";
	echo "Servidor MX: <span>".$mx."</span><br>";
	echo "Reverso do MX: <span>\t".get_reverse_ip(inverteIP($ipmx))."</span><br>";
	echo "IP do MX: <span>\t".$ipmx."</span><br>";
	echo "</div>\n";

	echo "<div class='subdiv'>";
	echo "Informações do e-mail recebido
		<br> Um dos dados abaixo não está, mas deveria estar em seu registro SPF para autorizar seu servidor a enviar mensagens.";
	echo "<div class='info'> HELO: <span>\t".$helo."</span><br>";
	echo "Reverso: <span>\t".$reverso."</span><br>";
	echo "IP: <span>\t".$ip."</span><br>";
	echo "</div></div>\n";
	echo "</div>\n";
}

// Cria uma saida com IP reverso
function inverteIP($ip){
	$parts = explode('.',$ip); 
	return implode('.', array_reverse($parts));
}

// Consulta SPFBL
function spfbl($ip, $remetente, $helo, $destinatario){
	include "config.php";
	error_reporting(E_ALL);

	// Porta do SPFBL
	$service_port = 9877;

	// Pega IP do host
	$address = gethostbyname($url);

	// Cria o socket TCP/IP
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
		echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
	}
	// Cria a conexão
	$result = socket_connect($socket, $address, $service_port);
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
	}
	// Prepara o comando a ser executado no SPFBL
	$in = "CHECK $ip $remetente $helo $destinatario\r\n";
	$out = '';

	// Realiza  a consulta no SPFBL de check.
	socket_write($socket, $in, strlen($in));

	echo "<div class='spfbl'> Checagem da requisição no SPFBL\n";
	// Imprime a saida do comando.
	while ($out = socket_read($socket, 2048)) {
		//echo "<pre>".$out."</pre>\n";
		processa_spfbl($out, $ip);
	}
	echo "</div>\n";
	echo "</div>\n";
	// fecha o socket.
	socket_close($socket);
}

// Processa a saida do SPFBL
function processa_spfbl($out, $ip) {
	$i=0; $control=0;
	$thisLine=""; 
	$pass = false; $white = false; $block=false; $red=false; $notprint = false;
	$lines = explode("\n", trim($out));
	foreach($lines as $line) {
		$itens = explode(" ", trim($line));
		foreach($itens as $item) {
			//if($i==0) echo "<br>";
			$thisLine = $thisLine.$item." ";
			if($item=="PASS") {
				echo "<div class='sucess left'>".$thisLine."</div>\n";
				$thisLine="&nbsp;&nbsp;&nbsp;&nbsp;";
				$pass = true;
			}elseif($item=="MATCH"){
				echo "<div class='warn left'>".$thisLine."</div>\n";
				$thisLine="&nbsp;&nbsp;&nbsp;&nbsp;";
			}elseif(preg_match('/(results|status)/', $item)){
				echo "<div class='subdiv left'>".$thisLine;
				$thisLine="&nbsp;&nbsp;&nbsp;&nbsp;";
				$control++;
			}elseif(preg_match("/(First)/", $thisLine) && preg_match("/(Considered)/", $item)){
				if(preg_match('/(BLOCK)/', $thisLine)){
					$block=true;
					$thisLine = str_replace(array('Considered', ''), '',$thisLine);
					echo "<div class='error left'>".$thisLine."</div>\n";
					$thisLine=$item." ";
				}
				if(preg_match('/(WHITE)/', $thisLine)){
					$white=true;
					$thisLine = str_replace(array('Considered', ''), '',$thisLine);
					echo "<div class='sucess left'>".$thisLine."</div>\n";
					$thisLine=$item." ";
				}
			}elseif($item=="GREEN"){
				echo "<div class='info left'>".$thisLine;
				$control++;
			}elseif($item=="YELLOW"){
				echo "<div class='warn left'>".$thisLine;
				$control++;
			}elseif($item=="RED"){
				echo "<div class='error left'>".$thisLine;
				$red=true;
				$control++;
			}elseif(preg_match("/(Considered|First)/", $item)){
				$thisLine=$item." ";
				echo "</div>\n";
				$notprint = true;
			}elseif($control==1){
				echo $item."</div>\n";
				$thisLine="&nbsp;&nbsp;&nbsp;&nbsp;";
			}
			$i++;
		}
		$i=0;$control=0;
	}
	if($notprint == true) {
		echo "</div>\n";
		echo "<div class='subdiv2'> Resultado da checagem SPFBL\n";
		if($white==true){
			echo "<div class='sucess2'> Há um WHITELIST e suas mensagens não estão sendo bloqueadas. </div>";
		}elseif($pass==true && $block==false && $red==false){
			echo "<div class='sucess2'> Suas mensagens não estão sendo bloqueadas. </div>";
		}elseif($pass==false && $block==false && $red==false){
			echo "<div class='warn2'> Há um erro na configuração do seu servidor, seu SPF é inválido. 
			<br> Corrija o registro SPF conforme <a target='_blank' href='http://www.ietf.org/rfc/rfc4408.txt'> RFC4408 </a>
			<br><br> As informações abaixo podem dar um indicativo dos dados a serem usados no SPF correto. </div>";
			echo "<div class='dica'> Dica: Considere adicionar <span style='color: black;'>".$ip."</span> a seu registro SPF atual. </div>";
			echo "<div class='info2'>Aprenda mais sobre SPF em <a target='_blank' href='http://www.antispam.br/admin/spf/'> AntiSpam.Br</a></div>";
		}elseif($block==true){
			include 'config.php';
			echo "<div class='error2'> Há um BLOQUEIO e suas mensagens estão sendo rejeitadas. 
				<br> Entre em contato com nosso suporte para verificar o desbloqueio.<br>
				<br> Telefone: ".$telefone."
				<br> E-mail: ".$suportemail."
				</div>";
		}
		if($red==true){
			echo "<div class='warn2'> Seu IP, domínio ou datacenter, está com baixa reputação. 
			<br> No momento suas mensagens estão sendo atrasadas ou marcadas como SPAM.
			<br><br> Evite enviar mais e-mails até que todos os problemas tenham sido tratados, pois sua reputação irá piorar e você poderá ser permanentemente bloqueado.
			<br> <br> Verifique a reputação do IP em <span><a target='_blank' href='http://multirbl.valli.org/lookup/".$ip."/.html'> http://multirbl.valli.org/lookup/".$ip."/.html</a></span>.
			</div>";
			echo "<div class='dica'> Dica: Caso não haja erros na sua configuração, entre em contato com o destinatário e solicite adição de seu domínio a WHITELIST.</div>";
		}
	}
}

// Verifica se o remetente utiliza tudo em um único servidor.
function check_servers($ip, $ipmx){
	if($ip == $ipmx) {
		echo "<div class='info'>Seu servidor cumpre as funções de MX e SMTP.</div>"; 
	}else{ 
		echo "<div class='info'>Você possui servidores MX e SMTP separados. <br>
		<br> Se seu servidor SMTP não estiver listado em seu registro SPF sua mensagem poderá ser negada. </div>";
	}
}

// Verifica MX
function check_spf_ip($hostname, $ip){
	$ok = 'ok';$processadoSpf=false;
    $txt_records = dns_get_record($hostname, DNS_TXT);
    if(empty($txt_records)) {
        echo "<div class='error'>Sem registros TXT. <br> Caso na sessão do SPFBL não exista nenhum resultado PASS, esse é o motivo da falha. <br>Crie o registro SPF conforme <a target='_blank' href='http://www.ietf.org/rfc/rfc1912.txt'> RFC4408 </a> </div>";$ok='err';
    }
    foreach($txt_records as $record) {
        if(array_key_exists('txt', $record)) {
            if(strpos($record['txt'], 'v=spf1') !== false) {
                if($record['txt']) {
                    echo "<div class='sucess'>SPF existente: ".$record['txt']."<br><br> Verifique se no teste do SPFBL um item retornou PASS, <br> caso não haja, corrija o registro SPF conforme <a target='_blank' href='http://www.ietf.org/rfc/rfc4408.txt'> RFC4408 </a> </div>";
                    $ok = 'ok';
					$processadoSpf=true;
                //    return true;
                }else{echo "<div class='error'>SPF inválido. <br> Corrija o registro SPF conforme <a target='_blank' href='http://www.ietf.org/rfc/rfc4408.txt'> RFC4408 </a> </div>"; $ok='err';$processadoSpf=true;}
            }
        }
    }
	if($processadoSpf==false){
		echo "<div class='error'>O domínio não possui registros TXT com a FLAG 'v=spf1' utilizado nos registros SPF. 
		<br>  
		<br> Crie o registro SPF conforme <a target='_blank' href='http://www.ietf.org/rfc/rfc1912.txt'> RFC4408 </a> </div>";$ok='err';
	}
	if($ok=='err'){ echo "<div class='info'>Aprenda mais sobre SPF em <a target='_blank' href='http://www.antispam.br/admin/spf/'> AntiSpam.Br</a></div>";}
}

// Verifica reverso
function check_reverse_ip($ip, $helo, $mx){
	$ok = 0;
	$reverseQuery = $ip.".in-addr.arpa"; 
	$normal_ip = inverteIP($ip);
    $ptr_records = dns_get_record($reverseQuery, DNS_PTR);
    if(empty($ptr_records)) {
        echo "<div class='error'>Sem registros PTR/Reverso para <span>".$reverseQuery." - ".$normal_ip."</span>. <br>
			<br> Era esperado: 
			<br> HELO [<span>".$helo."</span>] ou [<span>".$mx."</span>]
			<br> Crie o registro do reverso conforme <a target='_blank' href='http://www.ietf.org/rfc/rfc1912.txt'> RFC1912 </a> </div>";
    }
    foreach($ptr_records as $record) {
        if(array_key_exists('target', $record)) {
            if($record['target'] == $helo) {
                echo "<div class='sucess'> Registro PTR/Reverso válido. </div>";
                 $ok = 1;
             }else{echo "<div class='error'>Registro PTR/Reverso inválido. 
						<br> Seu Reverso atual: <span>".$record['target']."</span><br>
						<br> Era esperado: 
						<br> HELO [<span>".$helo."</span>] ou [<span>".$mx."</span>]
						<br> Corrija o reverso conforme <a target='_blank' href='http://www.ietf.org/rfc/rfc1912.txt'> RFC1912 </a> </div>"; $ok=1;}
        }
		return $record['target'];
    }
}

// Obtem reverso
function get_reverse_ip($ip){
	$ok = 0;
	$reverseQuery = $ip.".in-addr.arpa"; 
	$normal_ip = inverteIP($ip);
    $ptr_records = dns_get_record($reverseQuery, DNS_PTR);
    foreach($ptr_records as $record) {
 		return $record['target'];
    }
}

// Identifica o MX
function mxrecordValidate($domain){
	$arr= dns_get_record($domain,DNS_MX);
    if($arr[0]['host']==$domain&&!empty($arr[0]['target'])){
		return $arr[0]['target'];
    }
}

?>
      <hr>
      <div id="divfooter">
        <div id="divanuncio">
          Anuncie aqui pelo <a target='_blank' href='http://a-ads.com?partner=455818'>Anonymous Ads</a>
        </div>
        <div id="divpowered">
          Powered by <a target='_blank' href="http://spfbl.net/">SPFBL.net</a>
        </div>
      </div>
    </div>
  </body>
</html>
