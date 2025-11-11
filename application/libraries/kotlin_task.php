<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Java
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Kotlin_Task extends Task {
    public string $mainClassName;
    public string $additionalFiles;
    
    public function __construct($filename, $input, $params) {
        global $CI;

        $params['memorylimit'] = 0;    // Disregard memory limit - let JVM manage memory
        $this->default_params['numprocs'] = 256;     // Java 8 wants lots of processes
        $this->default_params['interpreterargs'] = array(
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m"
        );
        $this->default_params['main_class'] = null;

        // Extra global Java arguments
        if($CI->config->item('java_extraflags') != '') {
            array_push($this->default_params['interpreterargs'], $CI->config->item('java_extraflags')); 
        }

        if (isset($params['numprocs']) && $params['numprocs'] < 256) {
            $params['numprocs'] = 256;  // Minimum for Java 8 JVM
        }

        parent::__construct($filename, $input, $params);
    }

    public function prepare_execution_environment($sourceCode) {
        parent::prepare_execution_environment($sourceCode);

        // Superclass calls subclasses to get filename if it's
        // not provided, so $this->sourceFileName should now be set correctly.
        $extStart = strpos($this->sourceFileName, '.');  // Start of extension
        $this->mainClassName = substr($this->sourceFileName, 0, $extStart) . "Kt";
    }

    public function load_files($fileList) {
        parent::load_files($fileList);

        // Store each additional file so we can add
        $this->additionalFiles = "";
        foreach ($fileList as $file) {
            // $fileId is $file[0], but we don't need it
            $filename = $file[1];
            $this->additionalFiles = $this->additionalFiles . $filename . " ";
        }
    }

    public static function getVersionCommand() {
        return array('/opt/kotlinc/bin/kotlin -version', '/version "?([0-9._]*)/');
    }

    public function compile() {
        global $CI;

        // Extra global Javac arguments
        $extra_javacflags = $CI->config->item('javac_extraflags');

        $prog = file_get_contents($this->sourceFileName);
        $compileArgs = $this->getParam('compileargs');
        $cmd = '/opt/kotlinc/bin/kotlinc ' . $extra_javacflags . ' ' . implode(' ', $compileArgs) 
            . " {$this->sourceFileName}" . " {$this->additionalFiles}";
        list($output, $this->cmpinfo) = $this->run_in_sandbox($cmd);
        if (empty($this->cmpinfo)) {
            $this->executableFileName = $this->sourceFileName;
        }
    }

    // A default name for Kotlin programs. An unsual name is given to avoid
    // collisions with other names that might be used
    public function defaultFileName($sourcecode) {
        return 'RsDefaultMain.kt';
    }

    public function getExecutablePath() {
        return '/opt/kotlinc/bin/kotlin';
    }



    public function getTargetFile() {
        return $this->getParam('main_class') ?? $this->mainClassName;
    }

    // Get rid of the tab characters at the start of indented lines in
    // traceback output.
    public function filteredStderr() {
        return str_replace("\n\t", "\n        ", $this->stderr);
    }
};

