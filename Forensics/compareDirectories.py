#!/opt/local/bin/python

import argparse
import hashlib
import os
import pathlib
import sys


class DirectoryFiles:
	'Retrieve all of a directory’s files, recursively.'
	
	_paths_to_ignore = (
		"/.idea",
		"/.git",
		"/.DS_Store",
		# "/README.md",
		# "/Readme.txt",
		# "/readme.txt",
		# "/LICENSE.TXT",
		# "/version.txt",
		# "/AssemblyInfo.cs",
		# "/Program.cs",
		# "/app.config",
		# "/App.config",
		# "/Resources.resx",
		# "/Settings.Designer.cs",
		# "/Settings.settings",
		# "/Resources.Designer.cs",
		# "/_._",
		# "/packages.config",
		# "/.signature.p7s",
		# "/packages/Microsoft.",
		# "/packages/MSTest.",
		# "/packages/System.",
		# "/Autofac.",
		# "/Castle.Core",
		# "/Crc32.NET.",
		# "/DotNetty.",
		# "/EnterpriseLibrary.TransientFaultHandling",
		# "/Mono.Security.",
		# "/MSTest.Test",
		# "/MSTest.TestAdapter.",
		# "/MSTest.TestFramework.",
		# "/NETStandard.Library.",
		# "/Newtonsoft.Json.",
		# "/PCLCrypto.",
		# "/PInvoke.",
		# "/Polly.",
		# "/Swashbuckle.",
		# "/Twilio.",
		# "/Validation.",
		# "/WindowsAzure.ServiceBus.",
		# "/WindowsAzure.Storage.",
		# ".sql",
		# 'iPod Photo Cache',
		# '.jpg',
		# '.JPG',
		# '.CR2',
		# '.MOV',
		# '.mov',
		# '.AVI',
		# '.PNG',
		# '.pdf',
	)
	
	def __init__(self, path):
		path = os.path.realpath(path)
		
		if not os.path.exists(path):
			raise ValueError('The path "' + path + '" does not exist.')
		
		# remove_len = len(path) - len(os.path.basename(path))
		
		self.paths_per_file = {}
		for path, dirs, files in os.walk(path):
			for file in files:
				full_file_path = os.path.join(path, file)
				
				if not self._ignore_path(full_file_path):
					if file not in self.paths_per_file:
						self.paths_per_file[file] = []
					
					# Prevent path from being added twice.
					short_path = path  # [remove_len:]
					if short_path not in self.paths_per_file[file]:
						self.paths_per_file[file].append(short_path)
	
	def _ignore_path(self, haystack):
		for needle in self._paths_to_ignore:
			if needle in haystack:
				return True
		return False


# MAIN #################################################################################################################
try:
	parser = argparse.ArgumentParser()
	parser.add_argument('-o', nargs=1, type=str, help='output file name')
	parser.add_argument('paths', nargs=2, type=str, help='two directories to be compared')
	args = parser.parse_args()
	
	sys.stdout.write("Retrieving files\n")
	first = DirectoryFiles(args.paths[0])
	second = DirectoryFiles(args.paths[1])
	
	sys.stdout.write("Comparing file names\n")
	
	first_hashes = {}
	second_hashes = {}
	for file_name, first_paths in first.paths_per_file.items():
		
		# If file names match from first and second list:
		# Hash file contents only when file names match.
		if file_name in second.paths_per_file:
			
			for path in first_paths:
				full_path = path + '/' + file_name
				if full_path not in first_hashes:
					first_hashes[full_path] = hashlib.sha256(pathlib.Path(full_path).read_bytes()).digest()
			
			for path in second.paths_per_file[file_name]:
				full_path = path + '/' + file_name
				if full_path not in second_hashes:
					second_hashes[full_path] = hashlib.sha256(pathlib.Path(full_path).read_bytes()).digest()

# # Write out files and paths from first list.
# for path in first_paths:
# 	io_object.write(file_name + "\t" + path + "\n")
#
# # Write out files and paths
# for path in second.paths_per_file[file_name]:
# 	io_object.write(file_name + "\t" + path + "\n")

# if 'o' in args and args.o is not None:
# 	io_object = open(args.o, "w")
# else:
# 	io_object = sys.stdout
	
	sys.stdout.write("Comparing hash values and deleting\n")
	
	for fpath, fhash in first_hashes.items():
		for spath, shash in second_hashes.items():
			try:
				if fhash == shash:
					os.remove(spath)
			except Exception as e:
				print(e)
				
# if io_object is not sys.stdout:
# 	io_object.close()
	print('')

except Exception as e:
	print(e)
