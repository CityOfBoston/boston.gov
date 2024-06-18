<?php
//  throw new Exception('COB Test 500 Error!');
header('HTTP/1.1 500 Service Temporarily Unavailable', true, 500);
header('Status: 500 Service Temporarily Unavailable', true, 500);
header('Retry-After: 300');//300 seconds
