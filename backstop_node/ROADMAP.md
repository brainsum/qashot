#Node app roadmap
1. Local node app
    1. Server that accepts POST requests (command, engine, backstop.json path)
    1. Node has access to private_files and sites/default/files
    1. Images are not returned, only additional results
1. Remote node app
    1. Server accepts POST requests (command, engine, backstop.json as file or string)
    1. Node lives separately from main app, with no access to private_files and sites/default/files
    1. Node app updates paths from backstop.json and executes tests locally
    1. Sends back image data, and additional results in the response

###Additional todos:
* Move/copy templates from private_files to the node app
    * They are going to be in the same repo no matter what, so move would be less overhead when updating the scripts
