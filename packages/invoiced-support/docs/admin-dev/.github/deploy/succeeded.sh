#!/bin/bash

# Record deploy on Datadog
VERSION=`git rev-parse --short HEAD`
SANITIZED_COMMIT_MESSAGE=`git log -1 --pretty=%B`
SANITIZED_COMMIT_MESSAGE=`echo ${SANITIZED_COMMIT_MESSAGE} | tr "\n" " "`
AUTHOR=`git log -1 --pretty=format:'%ae'`
echo "Recording deploy on Datadog"
curl  -X POST -H "Content-type: application/json" \
-d "{
      \"title\": \"✅ Successful Deploy\",
    \"text\": \"Deployed version ${VERSION} on ${REPO_NAME}:${GITHUB_REF_NAME} branch\nCommit Message: ${SANITIZED_COMMIT_MESSAGE}\nAuthor: ${AUTHOR}\nBuild Log: ${GITHUB_JOB_URL}\",
      \"priority\": \"normal\",
    \"tags\": [\"env:${GITHUB_REF_NAME}\"],
      \"alert_type\": \"success\",
      \"source_type_name\": \"GITHBACTIONS\"
}" \
"https://api.datadoghq.com/api/v1/events?api_key=${DATADOG_API_KEY}"
