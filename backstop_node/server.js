'use strict';
// Access from PHP container with curl backstop_node:3000.

// /*
//  * @todo
//  * @see https://github.com/garris/BackstopJS#installing-backstopjs-locally
//  * Enable volumes from the php/nginx container, private_files and sites/default/files might be enough
//  * The server should listen to POSTs, and accept a backstop.json and some additional data (like command)
//  *   Additional data: use xvfb-run -a for slimer, etc.
//  * Spawn a child process with backstop, execute the command, return the result data as needed.
//  *   We need to get these back:
//  *     Bitmap file generation success.
//  *     passedTestCount
//  *     failedTestCount
//  */
// /*
//  * Check https://pkgs.alpinelinux.org/package/edge/community/x86/firefox-esr
//  */
//
// /*
//  @notes
//  // @todo: Add an admin form where the user can input the path of binaries.
//  // @todo: What if local install, not docker/server?
//  // With slimerjs we have to use xvfb-run.
//  $xvfb = '';
//  if ($testerEngine === 'slimerjs') {
//  $xvfb = 'xvfb-run -a ';
//  }
//
//  $backstopCommand = escapeshellcmd($xvfb . 'backstop ' . $command . ' --configPath=' . $entity->getConfigurationPath());
//
//  exec($backstopCommand, $execOutput, $status);
//
//  */
// https://www.tutorialspoint.com/nodejs/nodejs_first_application.htm
const serverInfo = require('./includes/server-info');
const http = require("http");
// const backstop = require('backstopjs');

http.createServer(function (request, response) {
  // Send the HTTP header
  // HTTP Status: 200 : OK
  // Content Type: text/plain
  response.writeHead(200, {'Content-Type': 'text/plain'});

  // Send the response body as "Hello World"
  response.end('Hello World\n');
}).listen(serverInfo.port);

// Console will print the message
console.log(`Running on http://${serverInfo.host}:${serverInfo.port}`);
