<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Login</title>
        <meta name="title" content="Login"/>
        <meta name="description" content=""/>
        <link rel="stylesheet" href="/css/styles.css">
    </head>
    <body><!-- Login form area -->
        <div class="PageWrapper">
            <div class="PagePanel">
                <div class="head"><h5 class="iUser">Login</h5></div>
                <form action="" id="valid" class="mainForm" method="POST">
                    <fieldset>
                        <div class="PageRow noborder">
                            <label for="req1">Username:</label>
                            <div class="PageInput"><input type="text" name="username" class="validate[required]" id="req1" /></div>
                            <div class="fix"></div>
                        </div>

                        <div class="PageRow noborder">
                            <label for="req2">Password:</label>
                            <div class="PageInput"><input type="password" name="pass" class="validate[required]" id="req2" /></div>
                            <div class="fix"></div>
                        </div>
                        <div class="PageRow noborder">
                            <input type="submit" value="Log me in" class="greyishBtn submitForm" />
                            <div class="fix"></div>
                        </div>
                    </fieldset>
                </form>
                
                <script>
                // Capture current puzzle for post-login redirect
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.getElementById('valid');
                    const currentPuzzle = localStorage.getItem('lastPlayedPuzzle');
                    
                    if (currentPuzzle) {
                        // Add hidden field to preserve puzzle
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'return_to_puzzle';
                        hiddenInput.value = currentPuzzle;
                        form.appendChild(hiddenInput);
                    }
                    
                    // Also try to detect current puzzle from URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const returnToPuzzle = urlParams.get('return_to_puzzle');
                    if (returnToPuzzle && !currentPuzzle) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'return_to_puzzle';
                        hiddenInput.value = returnToPuzzle;
                        form.appendChild(hiddenInput);
                    }
                });
                </script>
            </div>
        </div>
        <div class="fix"></div>
    </body>
</html>
