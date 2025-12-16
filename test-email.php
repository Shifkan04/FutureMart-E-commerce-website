// test-email.php
   <?php
   require_once 'vendor/autoload.php';
   require_once 'config.php';
   require_once 'EmailHelper.php';

   try {
       $emailHelper = new EmailHelper();
       $result = $emailHelper->sendOTPEmail(
           'Shifkanm04@gmail.com', 
           'Test User', 
           '123456', 
           15
       );
       
       if ($result) {
           echo "Email sent successfully!";
       } else {
           echo "Email sending failed!";
       }
   } catch (Exception $e) {
       echo "Error: " . $e->getMessage();
   }
   ?>