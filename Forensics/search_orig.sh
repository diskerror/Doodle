#!/bin/bash

origFieldsFile='/Users/reid/Desktop/Capsicum Woodbury work/adcs_addr_script unique fields.sql'
searchTermsFile='/Users/reid/Desktop/Capsicum Woodbury work/adcs_addr_script unique fields with wildcards.sql'

aew_dd_to_search='/Users/reid/Desktop/AEW csv'
# source_to_search='/Users/andrewzager/Desktop/source code review'
# source_to_search='/Users/reid/Desktop/Schemas'

terms=$(cat "$searchTermsFile")

# echo '' > "$aewddRes"
# echo '' > "$srccRes"

for term in $terms
do
# 	t_sub=${term//'_'/'\w{0,10}[_\s]{0,4}[\w\s]{0,10}'}
# 	t_sub=${term//'_'/'[_[:space:]]{0,4}'}
	t_sub=${term//'_'/'[^,]{0,6}'}
# 	egrep -ioh -m 1 "$t_sub" "$origFieldsFile"
	origField=$(egrep -ioh -m 1 "$t_sub" "$origFieldsFile")
# 	echo -n $origField "	" >> "$srccRes"

# 	found=$(egrep -iroh -m 1 --exclude='.*' "$t_sub" "$aew_dd_to_search")

	if [ -z "$1" ]; then
		echo 'no input'
		exit
	fi
	found=$(egrep -iroh -m 1 --exclude='.*' "$t_sub" "$1")
	
	if [  ! -z "$found" ]
	then
		echo "$origField  -  $t_sub:"
	
		IFS=$'\n'
		for f in $found
		do
			echo -n "$f · "
		done
		echo
		echo
	fi

# 	echo $(egrep -iorhz -m 1 --exclude='.*' '$t_sub' "$source_to_search") >> "$srccRes"
done

echo
