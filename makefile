cppheal: cpp/*.php cppheal.php
	echo '#!/usr/bin/php' > cppheal
	phpinc -l lib cppheal.php >> cppheal
	chmod u+x cppheal
