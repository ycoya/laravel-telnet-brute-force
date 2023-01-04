
# Laravel Telnet Brute Force

Recovering password from telnet service by using
brute force.

This was made to meet my own needs, it is not expected
to be used in a real web application. This was more to learn
and practice to build packages. If someone needs it, well
here it is.

This package contains two artisan console command which
will run the brute force attack. 

One command will use an users and passwords lists
from files and the other one generate the passwords
internally.

It is important to say, that the steps used to determine
that we succeed in guessing the password is by checking
we do not receive the ´login´ back from the telnet reply.
This way we could have a $, #, or C:\> as prompt. So this is
for those cases where we do not know what we will see when we
log in. We could have false positive too. I'm not responsible
if someone use this in a wrong way. 





## Installation

```bash
  composer require ycoya/laravel-telnet-brute-force
```
    
## Requirements
php 8.0+
## Documentation

## `Brute Force With Dictionary`

```
php artisan telnet:attack-dict --host=172.0.0.1 
--userDb=utils/users.txt --passDb=utils/passwords.txt
```
To receive help from command, just type
```
 php artisan help telnet:attack-dict
```

The host option will define the machine target. 
The options --userDB and --passDb are the path
from where the users and passwords will be taken. 

The procedure is: first user is selected and then this user
is tested through all the passwords from password.txt,
if nothing is found, then it will take the second user,
and it repeats the same procedure.
We can also type a full path for example:

--userDb=C:\brute-force\dictionary\utils\users.txt 

same for

--passDb=C:\brute-force\dictionary\utils\passwords.txt 

Or it could be relative to the laravel application root folder.
like this:

 --userDb=utils/users.txt 

If this file is not found in this path, then it will try to search in
storage/app folder. There are three options then:

1-full path

2-relative path to laravel root folder

3-relative path to (laravel_root_folder)/storage/app.


The files should have the structure of one string by line.
Example:

In `users.txt`, we could have.

root

john

juan

...

In `passwords.txt`, we could have.

admin

password

1234

...

This command saves the index of current user and password from users
and password list used.
If the command is interrupted or we stop it for any reason we could
resume from where we left of automatically.

## `Brute Force With Password Generation`
```
php artisan telnet:attack-gp --host=172.0.0.1 --user=root
 -m CharMap.txt --min=1 --max=5
```

The host option will define the machine target.

--user option is the user that will be use with all
the password generated.

-m|char_map_path is the path to the file
where we will obtain the chars to generate the passwords
  
This is the same as before, char_map_path could contain:

1-full path

2-relative path to laravel root folder

3-relative path to (laravel_root_folder)/storage/app.

The structure for the charMap is an array:

charMap.txt

    ["a","b","c","d","e","f","g","h","i","j","k","l","m",
    "n","o","p","q","r","s","t","u","v","w","x","y","z"]

The commmand will use these chars to compose the password.

--min option is to set the start length of the password to compose.

--max option is to set the final lenght of the password to compose.

From the above example we will get passwords from one length,
when all chars are passed, then it will move to generate
 password of two lengths, using the charMap combining.
Example:
 aa, ab, ac...etc, when this finishes then it will
 use a password of lenght 3, and so on until the max value
For this example it is five. length 5.

If we only want to use specific length, let's say
a password of 4 chars to test all combinations.
Then we can pass as options

    php artisan telnet-attack-gp --min=4 --max=4

The same value for both options.

If we pass --max value only then we will have password
length from 1 to this max value,
So in the example above we could omit --min=value any combinations
of these we could use.


There is a --debug option that will output to laravel.log
more info if needed, but it won't display it in console.

This command is saving the progress of the generated password
and the times used to generate it.
So if the command is interrupted or
we stop it for any reason we could resume
from where we left of automatically.

If we need to restart again then the --reset option is
 the one for this.

This progress is saved in storage/app/telnet-brute-force-gp folder.
 These are the basics. For additional help run:

    php artisan help telnet:attack-dict
or
    
    php artisan help telnet:attack-gp 

for futher options.
