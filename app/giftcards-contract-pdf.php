<?php
declare(strict_types=1);
require_once __DIR__.'/vendor/tfpdf/font/unifont/ttfonts.php';
require_once __DIR__.'/vendor/tfpdf/tfpdf.php';

/** The Word template remains the content source; public contract downloads are PDF. */
final class GiftContractPdf extends tFPDF {
 public string $contractNumber='';
 public function Footer() {
  $this->SetY(-12);$this->SetFont('DejaVu','',8);$this->SetTextColor(90);
  $this->Cell(0,5,$this->contractNumber.' · '.$this->PageNo(),0,0,'C');$this->SetTextColor(0);
 }
 public function lines(string $text,float $width):array {
  $out=[];
  foreach(explode("\n",str_replace("\r",'',trim($text))) as $paragraph){
   $line='';foreach(preg_split('/\s+/u',$paragraph,-1,PREG_SPLIT_NO_EMPTY) as $word){
    if($line!==''&&$this->GetStringWidth($line.' '.$word)>$width){$out[]=$line;$line='';}
    // Break long email addresses, URLs and identifiers rather than clipping them.
    foreach(mb_str_split($word) as $char){if($line!==''&&$this->GetStringWidth($line.$char)>$width){$out[]=$line;$line='';}$line.=$char;}
    $line.=' ';
   }
   $out[]=rtrim($line);
  }
  return $out;
 }
 public function room(float $height):void {if($this->GetY()+$height>277)$this->AddPage();}
 public function paragraph(string $text,bool $heading=false):void {
  $this->SetFont('DejaVu',$heading?'B':'',$heading?12:10);
  $lines=$this->lines($text,170);$this->room($heading?min(count($lines)*6+12,70):10);
  foreach($lines as $line){$this->room(5.3);$this->Cell(170,5.3,$line,0,1);}
  $this->Ln($heading?3:2);
 }
 public function signature(?array $image,float $x,float $lineY,float $box=34):void {
  if(!$image)return;
  $scale=min($box/$image['width'],$box/$image['height']);$w=$image['width']*$scale;$h=$image['height']*$scale;
  // Absolute drawing overlays the signature line; it never changes image proportions.
  $this->Image($image['path'],$x,$lineY-$h/2,$w,$h,'PNG');
 }
 public function signaturePair(string $agent,string $issuer,?array $image):void {
  $this->SetFont('DejaVu','',10);$this->room(45);$top=$this->GetY();$lineY=$top+17;
  $this->SetXY(20,$lineY);$this->Cell(170,5.3,preg_replace('/_{5,}/','____________________________',$agent),0,1);$this->signature($image,39,$lineY+2);
  $this->SetXY(20,$top+36);foreach($this->lines($issuer,170) as $line)$this->Cell(170,5.3,$line,0,1);$this->Ln(4);
 }
 public function table(array $rows,?array $image):void {
  $requisites=($rows[0][0]??'')==='Агент';
  foreach($rows as $rowIndex=>$cells){
   $signing=$image!==null&&isset($cells[0])&&str_contains($cells[0],'Подпись');
   $this->SetFont('DejaVu',$rowIndex===0?'B':'',9);
   $widths=count($cells)===2?($requisites?[85,85]:[49,121]):array_fill(0,count($cells),170/count($cells));
   $lines=[];foreach($cells as $k=>$text)$lines[]=$this->lines($text,$widths[$k]-5);
   $count=max(array_map('count',$lines));$height=max(8,$count*4.8+5,$signing?43:0);
   $this->room(min($height,50));$offset=0;
   do {
    $available=max(1,(int)floor((277-$this->GetY()-5)/4.8));$take=min($count-$offset,$available);
    if($take<1){$this->AddPage();continue;}
    $partHeight=max(8,$take*4.8+5,$signing?43:0);$this->room($partHeight);$y=$this->GetY();$x=20;
    foreach($lines as $k=>$column){
     $this->SetDrawColor(150);$this->Rect($x,$y,$widths[$k],$partHeight);$this->SetXY($x+2.5,$y+2.5);
     $slice=array_slice($column,$offset,$take);foreach($slice as $line){$this->SetX($x+2.5);$this->Cell($widths[$k]-5,4.8,$line,0,1);}
     if($signing&&$k===0)$this->signature($image,$x+22,$y+25,32);
     $x+=$widths[$k];
    }
    $this->SetXY(20,$y+$partHeight);$offset+=$take;
    if($offset<$count)$this->AddPage();
   }while($offset<$count);
  }
  $this->Ln(4);
 }
}

function gc_pdf_node_text(DOMNode $node):string {
 $out='';foreach($node->childNodes as $child){
  if($child->localName==='t')$out.=$child->textContent;
  elseif($child->localName==='br'||$child->localName==='cr')$out.="\n";
  elseif($child->localName==='tab')$out.=' ';
  elseif($child->localName!=='drawing')$out.=gc_pdf_node_text($child);
 }
 return $out;
}
function gc_contract_pdf_from_docx(string $source,string $target,string $number,?array $signature=null):void {
 $zip=new ZipArchive();if($zip->open($source)!==true)throw new RuntimeException('CONTRACT_TEMPLATE_INVALID');
 $embedded=null;
 try {
  $xml=$zip->getFromName('word/document.xml');
  // Existing stored contracts also download as PDF, using their own approved image.
  if(!$signature&&($bytes=$zip->getFromName('word/media/pina-approved-signature.png'))!==false){
   $embedded=tempnam(dirname($target),'pina-sign-');if($embedded===false)throw new RuntimeException('CONTRACT_TEMP_UNAVAILABLE');file_put_contents($embedded,$bytes);chmod($embedded,0600);$size=getimagesize($embedded);
   if($size)$signature=['path'=>$embedded,'width'=>$size[0],'height'=>$size[1]];
  }
  $dom=new DOMDocument();if(!$dom->loadXML($xml,LIBXML_NONET))throw new RuntimeException('INVALID_DOCUMENT_XML');
  $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$body=$xp->query('//w:body')->item(0);
  $pdf=new GiftContractPdf();$pdf->contractNumber=$number;$pdf->SetMargins(20,18,20);$pdf->SetAutoPageBreak(false);$pdf->SetTitle($number,true);
  $pdf->AddFont('DejaVu','','DejaVuSans.ttf',true);$pdf->AddFont('DejaVu','B','DejaVuSans-Bold.ttf',true);$pdf->AddPage();
  $nodes=iterator_to_array($body->childNodes);
  for($n=0;$n<count($nodes);$n++){
   $node=$nodes[$n];
   if($node->localName==='tbl'){
    $rows=[];foreach($xp->query('./w:tr',$node) as $row){$cells=[];foreach($xp->query('./w:tc',$row) as $cell){$paras=[];foreach($xp->query('./w:p',$cell) as $p)$paras[]=gc_pdf_node_text($p);$cells[]=implode("\n",array_filter($paras,fn($p)=>trim($p)!==''));}$rows[]=$cells;}
    $pdf->table($rows,$signature);continue;
   }
   if($node->localName!=='p')continue;$text=trim(gc_pdf_node_text($node));if($text==='')continue;
   if(str_starts_with($text,'Агент:')){
    $next=$n+1;while(isset($nodes[$next])&&trim(gc_pdf_node_text($nodes[$next]))==='')$next++;
    $issuer=isset($nodes[$next])?trim(gc_pdf_node_text($nodes[$next])):'';
    if(str_starts_with($issuer,'Эмитент:')){$pdf->signaturePair($text,$issuer,$signature);$n=$next;continue;}
   }
   if(str_starts_with($text,'Приложение '))$pdf->AddPage();
   if(preg_match('/^14\s+Реквизиты/u',$text))$pdf->room(140);
   $style=$xp->query('./w:pPr/w:pStyle/@w:val',$node)->item(0)?->nodeValue??'';
   $heading=preg_match('/^(?:Heading|Title|Subtitle)/i',$style)||preg_match('/^\d+\s+[^.]/u',$text)||str_starts_with($text,'Приложение ');
   // Separate runs in the original title need a visible word boundary.
   if(str_starts_with($text,'Рамочный агентский договор'))$text=str_replace('договоро подарочных','договор о подарочных',$text);
   $pdf->paragraph($text,(bool)$heading);
  }
  $pdf->Output('F',$target);chmod($target,0600);
 }finally{$zip->close();if($embedded!==null&&is_file($embedded))unlink($embedded);}
}
function gc_generate_contract_pdf(string $template,string $target,array $issuer,array $co,?array $signature):void {
 $docx=tempnam(dirname($target),'pina-contract-');if($docx===false)throw new RuntimeException('CONTRACT_TEMP_UNAVAILABLE');
 try{gc_generate_contract($template,$docx,$issuer,$co,$signature);gc_contract_pdf_from_docx($docx,$target,$issuer['contract_number'],$signature);}
 finally{if(is_file($docx))unlink($docx);}
}

function gc_send_contract_pdf(array $doc,string $number):never {
 if($doc['mime']==='application/pdf')gc_send_document($doc,$number.'.pdf');
 if(!gc_contract_file_valid($doc))throw new ApiError('Не удалось проверить документ',503);
 $target=tempnam(sys_get_temp_dir(),'pina-download-');
 try{gc_contract_pdf_from_docx(gc_storage().'/'.$doc['storage_name'],$target,$number);$bytes=file_get_contents($target);}
 finally{if(is_file($target))unlink($target);}
 header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9_.-]/','_',$number).'.pdf"');header('Content-Length: '.strlen($bytes));header('Cache-Control: private, no-store');echo $bytes;exit;
}
