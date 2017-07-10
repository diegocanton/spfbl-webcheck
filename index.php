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
        <p id="titulo">Procurar um relatório de erro.</p>
		<div class='warn'> Atenção: A busca só opera para a data atual.</div>
      </div>
		<hr>
		<div id="divcaptcha">
		<form id="rendered-form" method="get" action="/busca.php" target="_blank">
			<div>
				<label>&nbsp;&nbsp;Remetente<span>*</span></label>
				<input type="email" name="rem" id="rem" required="required" aria-required="true">
			</div>
			<div>
				<label >Destinatário<span>*</span></label>
				<input type="email" name="dest" id="dest" required="required" aria-required="true">
			</div>
			<div >
				<button type="submit" name="submit" id="btngo">Consultar</button>
			</div>
		</form>
		</div>
		
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
