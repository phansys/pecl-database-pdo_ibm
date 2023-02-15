--TEST--
pdo_ibm: Select LOBs, including null and 0-length
--SKIPIF--
<?php require_once('skipif.inc'); ?>
--FILE--
<?php
	require_once('fvt.inc');

	class Test extends FVTTest
	{
		public function connect($autoCommit=true, $useLibl=false, $useIsolation=false)
		{
			$options = array(/*PDO::ATTR_AUTOCOMMIT=>$autoCommit,*/ PDO::ATTR_PERSISTENT => true);
			$this->db = new PDO($this->dsn, $this->user, $this->pass, $options);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// $this->db->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);
			// $this->db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
			return $this->db;
		}

		public function runTest()
		{
			$this->connect();

			try {
				/* Drop the test table, in case it exists */
				$drop = 'DROP TABLE animals';
				$result = $this->db->exec( $drop );
			} catch( Exception $e ){}

			/* Create the test table */
			$create = 'CREATE TABLE animals (id INTEGER, my_clob clob, my_blob blob)';
			$result = $this->db->exec( $create );

			$clobContent = str_repeat('c', 4 * 8192);
			$blobContent = str_repeat('b', 4 * 8192);

			$data = array (
				array(
					array(PDO::PARAM_INT, 1),
					array(PDO::PARAM_STR, 'this is the clob that never ends...'),
					array(PDO::PARAM_STR, 'this is the blob that never ends...'),
				),
				array(
					array(PDO::PARAM_INT, 2),
					array(PDO::PARAM_STR, null),
					array(PDO::PARAM_STR, null)
				),
				array(
					array(PDO::PARAM_INT, 3),
					array(PDO::PARAM_STR, ''),
					array(PDO::PARAM_STR, '')
				),
				array(
					array(PDO::PARAM_INT, 4),
					array(PDO::PARAM_LOB, fopen('data://text/plain,' . $clobContent, 'r')),
					array(PDO::PARAM_LOB, fopen('data://text/plain,' . $blobContent, 'r'))
				)
			);

			$stmt = $this->db->prepare('insert into animals (id, my_clob, my_blob) values (?, ?, ?);');

			print "inserting\n";
			foreach ($data as $row) {
				foreach ($row as $position => $value) {
					$stmt->bindValue($position + 1, $value[1], $value[0]);
				}
				$stmt->execute();
			}

			print "succesful\n";
			/*
			print "running query\n";

			$stmt = $this->db->prepare( 'select id,my_clob,my_blob from animals' );

			$rs = $stmt->execute();

			$count = 0;
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				var_dump( $row['ID'] );

				// this is a temporary workaround
				// until zero-length/lob stream
				// issue is fixed
				if ($count < 2) {
				   var_dump( $row['MY_CLOB'] );
				   var_dump( $row['MY_BLOB'] );
				}
				// suppressed deprecation message for NULL on 8.1
				var_dump(@strpos($row['MY_CLOB'], 'lob'));
				$count++;
			}

			print "succesful\n";
			*/
			print "running query\n";

			$stmt = $this->db->prepare('SELECT id, my_clob, my_blob FROM animals;');

			$rs = $stmt->execute();

			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $i => $row) {
				var_dump($row['ID']);

				$clob = $row['MY_CLOB'];

				if (is_string($clob)) {
					$fp = fopen('php://temp', 'rb+');
					assert(is_resource($fp));
					fwrite($fp, $clob);
					fseek($fp, 0);
					$clob = $fp;
				}

				if (is_resource($clob)) {
					$clob = stream_get_contents($clob);
				}

				$blob = $row['MY_BLOB'];

				if (is_string($blob)) {
					$fp = fopen('php://temp', 'rb+');
					assert(is_resource($fp));
					fwrite($fp, $blob);
					fseek($fp, 0);
					$blob = $fp;
				}

				if (is_resource($blob)) {
					$blob = stream_get_contents($blob);
				}

				var_dump($clob);
				var_dump($blob);
			}

			print "done\n";
		}
	}

	$testcase = new Test();
	$testcase->runTest();
?>

--EXPECTF--
inserting
succesful
running query
string(1) "1"
string(35) "this is the clob that never ends..."
string(35) "this is the blob that never ends..."
string(1) "2"
NULL
NULL
string(1) "3"
string(0) ""
string(0) ""
string(1) "4"
string(32768) "cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc%s"
string(32768) "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb%s"
done

