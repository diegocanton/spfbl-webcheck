<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" type="image/png" href="http://spfbl.ensite.com.br/favicon.png">
    <title>Serviço SPFBL</title>
    <link rel="stylesheet" href="http://spfbl.ensite.com.br/style.css">
	<link rel="stylesheet" href="/notify.css">
  </head>
  <body>
    <div id="container">
      <div id="divlogo">
        <img src="http://spfbl.ensite.com.br/logo.png" alt="Logo">
      <hr>
      </div>
      <div id="divmsg">
        <p id="titulo">Procurar um relatório de erro.</p>
		<div class='warn'> Atenção: A busca só opera para a data atual.</div>
      </div>
		<hr>
<?php
// Nossas variáveis

$remetente = filter_var(urldecode($_GET['rem']),FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);

$destinatario = filter_var(urldecode($_GET['dest']),FILTER_VALIDATE_EMAIL,FILTER_SANITIZE_EMAIL);

if( !empty($remetente) && !empty($destinatario)){
	iniciar($ip, $remetente, $helo, $destinatario);
}else{
	echo "<div class='warn'> Sem informações para processar. </div>";
}

function iniciar($ip, $remetente, $helo, $destinatario){
	echo "<div class='info'> Buscando logs que contenham 
		<br> Remetente: ".$remetente."
		<br> Destinatario: ".$destinatario."
		</div>\n";
		
	// Lê um arquivo em um array.  
	// /var/log/spfbl/spfbl.2017-07-08.log
	$log = "/var/log/spfbl/spfbl.".date('Y-m-d').".log";

	$lines = file($log);

	$existLog = false; $printline=false; $printBlock=false; $printOk = false;
	$thisLine = "";$i=0;
	$searchd = "/".$destinatario."/";
	$searchr = "/".$remetente."/";
	if ($lines) { 
		foreach ($lines as $line) {
			if(preg_match('/(SPFTCP)([0-9]{3})( SPFBL)/', $line)){
				$itens = explode(" ", trim($line));
				$x=0;
				foreach($itens as $item) {
					$item = str_replace(array('\'', '"'), '',$item); // Limpa quotes
					$item = str_replace(array('\n', ''), '',$item); // Limpa \n
					if($x==0 | $x==8 | $x==9 | $x==10 | $x==13){
						$thisLine = $thisLine.$item."<br>\n";
						if($x==8){
							$ip=$item;
						}
						if($x==10){
							$helo=$item;
						}
						if($x==14){ // Define a URL do SPFBL
							$urlSPFBL=$item;
						}
					}
					if($item==$remetente){
						//$thisLine = $thisLine.$item." ";
					}elseif(preg_match($searchd, $item) && preg_match($searchr, $thisLine)){
						//$thisLine = $thisLine.$item." ";
						$existLog=true;
						$printline=true;
					}elseif($item=="PASS" | $item=="WHITE" ){
						$printOk = true;
					}elseif($item=="BLOCKED" | $item=="FAIL" | $item=="SOFTFAIL"){
						$printBlock = true;
					}
					$x++;
				}
				if($printline==true){
					if($printOk==true){
						echo "<div class='sucess'>Linha #<b>".$i."</b> : " . $thisLine . "
						<br> <a target='_blank' href='/consulta.php?ip=".$ip."&rem=".$remetente."&helo=".$helo."&dest=".$destinatario."'> Consultar configuração </a>
						</div>\n";
					}elseif($printBlock==true){
						echo "<div class='error'>Linha #<b>".$i."</b> : " . $thisLine . "
						<br> <a target='_blank' href='/consulta.php?ip=".$ip."&rem=".$remetente."&helo=".$helo."&dest=".$destinatario."'> Consultar configuração </a>
						</div>\n";
					}else{
						echo "<div class='warn'>Linha #<b>".$i."</b> : " . $thisLine . "
						<br> <a target='_blank' href='/consulta.php?ip=".$ip."&rem=".$remetente."&helo=".$helo."&dest=".$destinatario."'> Consultar configuração </a>
						</div>\n";
					}
				}
				$thisLine="";
				$printline=false; $printBlock=false; $printOk=false;
				$ip = ""; $helo = "";
			}
			$i++;
		}
		if($existLog==false){
			echo "<div class='warn'> Sem Logs para a busca. </div>";
		}
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
