<?php

passthru("wp eval 'UMCloudflare::purgeAll(\"/\");' 2>&1");
