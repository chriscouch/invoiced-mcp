#!/bin/bash
bundle=${1:-265184}
rm -rf tmp src.zip \
&& npx babel ./src --ignore src/definitions --out-dir tmp/src --extensions '.ts,.js';
if [ "$bundle" != "test" ]; then
  find ./tmp/src -type f -print0 | xargs -0 sed -i '' -e "s~tmp/src~/.bundle/$bundle/src~g" \
  && cd tmp \
  && zip -r ../src.zip src
fi