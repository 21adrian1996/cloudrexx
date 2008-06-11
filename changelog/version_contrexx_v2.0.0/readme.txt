12.03.2008 Livecam Anpassungen
---------------------------------------
In der Contentseite f�rs Modul Livecam muss der Link f�r das Anzeigebild und die Archivbilder folgendermassen angepasst werden:

Anzeigebild:
<a href="[[LIVECAM_IMAGE_LINK]]" title="[[LIVECAM_IMAGE_TEXT]]" [[LIVECAM_IMAGE_LIGHTBOX]]><img width="[[LIVECAM_IMAGE_SIZE]]" border="0" alt="[[LIVECAM_IMAGE_TEXT]]" src="[[LIVECAM_CURRENT_IMAGE]]" /></a>

Archivbilder:
<a href="[[LIVECAM_PICTURE_URL]]" title="[[LIVECAM_PICTURE_TIME]]" [[LIVECAM_IMAGE_LIGHTBOX]]><img src="[[LIVECAM_THUMBNAIL_URL]]" width="[[LIVECAM_THUMBNAIL_SIZE]]" border="0" alt="[[LIVECAM_PICTURE_TIME]]" /></a>

Zudem muss im Form-Tag die URL angepasst und &amp;cmd=[[CMD]] angeh�ngt werden:
<form action="index.php?section=livecam&amp;cmd=[[CMD]]" method="post" name="form">

Das gleiche gilt f�r den Aktualisieren-Link:
<a href="index.php?section=livecam&amp;cmd=[[CMD]]" onclick="javascript:document.location.reload();">Aktualisieren</a>


12.03.2008 Market Anpassungen
---------------------------------------
Die Contentseite (?section=market&cmd=confirm) wird neu in zwei Bl�cke unterteilt:

Block 1: <!-- BEGIN codeForm --><!-- END codeForm --> Dieser Block beinhaltet die bisherige Eingabeform f�r den Freischaltcode.

Block 1: <!-- BEGIN infoText --><!-- END infoText --> Dieser Block beinhaltet lediglich Text zur Information, dass der Block eingetragen wurde und nach Pr�fung aufgeschaltet werde.


11.06.2008 Frontend Editing Anpassungen
---------------------------------------
Frontend Editing
Contrexx wurde f�r die Version 2.0 um eine Frontend Editing-Funktion erg�nzt. Um diese zu verwenden m�ssen die folgenden Platzhalter in der index.html Ihres Designs eingef�gt werden:
  
1) [[LOGIN_INCLUDE]] innerhalb des <head>-Tags
2) [[LOGIN_CONTENT]] vor dem schliessenden </body>-Tag
3) [[LOGIN_URL]] dort, wo Sie Ihren Login-Link postionieren m�chten.
