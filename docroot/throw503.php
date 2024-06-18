<?php
  header('HTTP/1.1 503 Service Temporarily Unavailable', true, 503);
  header('Status: 503 Service Temporarily Unavailable', true, 503);
  header('Retry-After: 300');//300 seconds
