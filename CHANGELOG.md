# 2.0

* run() only accepts Request instances
* ->path(array('...', ...)) paths are not supported
* If we get no response, but managed to parse an url without an error, return an 501 Not Implemented status

* Every URI part MUST have a path callback
