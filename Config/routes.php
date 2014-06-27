<?php

Router::connect(
	'/upload/:action',
	array('controller' => 'upload', 'plugin'=>'upload', 'admin' => true)
);
Router::connect(
	'/upload/:action/*',
	array('controller' => 'upload', 'plugin'=>'upload', 'admin' => true)
);
