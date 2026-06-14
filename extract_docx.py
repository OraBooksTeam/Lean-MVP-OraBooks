import zipfile
import xml.etree.ElementTree as ET
import sys

path = r'C:\Users\TaxOra-DEOWM01\Desktop\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\Lean MVP OraBooks\Documentation\OraBooks_Lean_ MVP_SL_Detailed_Developer_Drafts_FINAL.docx'

z = zipfile.ZipFile(path, 'r')
xml_content = z.read('word/document.xml')

root = ET.fromstring(xml_content)

text_parts = []
for para in root.iter('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}p'):
    texts = []
    for t in para.iter('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}t'):
        if t.text:
            texts.append(t.text)
    if texts:
        text_parts.append(''.join(texts))

full_text = '\n'.join(text_parts)

with open('document_extracted.txt', 'w', encoding='utf-8') as f:
    f.write(full_text)

print(f"Extracted {len(text_parts)} paragraphs to document_extracted.txt")
