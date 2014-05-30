# Koken related crons
# Make sure this file has a blank newline at the end

# CACHE CLEANUP
# This empties cached images that have not been accessed in the last 10 days
# Runs every day at 02:10 UTC
10 2 * * * www-data find /usr/share/nginx/www/storage/cache/images/* -atime +10 -exec rm {} \;
