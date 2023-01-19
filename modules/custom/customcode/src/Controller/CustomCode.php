<?php

namespace Drupal\customcode\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Defines HelloController class.
 */
class CustomCode extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public function content() {
    $html = 'this is my <b>first</b>downloadable pdf';
$mpdf = new \Mpdf\Mpdf(['tempDir' => 'sites/default/files/pdfimages']); $mpdf->WriteHTML($html);
$mpdf->Output('print-challan-receipt.pdf', 'D');
Exit;
  }

}
