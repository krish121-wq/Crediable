const fs = require('fs');
const js = fs.readFileSync('test_script_clean.js', 'utf8');

let stack = [];
let lines = js.split('\n');
for (let i = 0; i < lines.length; i++) {
    let line = lines[i];
    for (let j = 0; j < line.length; j++) {
        let char = line[j];
        if (char === '{') stack.push({ char, line: i + 1 });
        else if (char === '}') {
            if (stack.length === 0 || stack[stack.length - 1].char !== '{') {
                console.log('Unmatched } at line ' + (i + 1));
            } else {
                stack.pop();
            }
        }
        else if (char === '(') stack.push({ char, line: i + 1 });
        else if (char === ')') {
            if (stack.length === 0 || stack[stack.length - 1].char !== '(') {
                console.log('Unmatched ) at line ' + (i + 1));
            } else {
                stack.pop();
            }
        }
    }
}
if (stack.length > 0) {
    console.log('Unclosed brackets/braces:');
    console.log(stack);
} else {
    console.log('Brackets and braces match perfectly!');
}
