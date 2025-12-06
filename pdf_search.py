#!/usr/bin/env python3
import sys
import os
import argparse
from pdfminer.high_level import extract_text
import re

def main():
    parser = argparse.ArgumentParser(description='Search/Extract codes from PDFs')
    parser.add_argument('code', nargs='?', help='Specific code to search for')
    parser.add_argument('--ocr', action='store_true', help='Use OCR (placeholder)')
    parser.add_argument('--strict', action='store_true', help='Strict mode for code matching')
    
    args = parser.parse_args()
    
    # Base directory for uploads
    base_dir = os.path.join(os.getcwd(), 'uploads')
    
    results = []
    
    # If a specific code is provided, we search for it (legacy behavior support)
    # But the new PHP script expects text output for the "extraction" mode.
    # The output format for "extraction" seems to be a list of found codes.
    
    for root, dirs, files in os.walk(base_dir):
        for filename in files:
            if not filename.lower().endswith('.pdf'):
                continue
                
            filepath = os.path.join(root, filename)
            
            try:
                text = extract_text(filepath)
                
                # Logic for extraction
                # We assume we are looking for patterns like "Ref: XXXXX"
                # Adjust regex based on strict mode if needed
                
                if args.strict:
                     # Example strict: Must look exactly like a code
                     # Using the pattern seeing in previous versions: Ref: CODE /
                     pattern = r'Ref:\s*([^/\s]+)'
                else:
                     # Relaxed: maybe just sequences of digits or alphanumeric?
                     # For now, sticking to the Ref pattern but maybe more reliable
                     pattern = r'Ref:\s*([^/\s]+)'

                matches = re.findall(pattern, text)
                
                for m in matches:
                    code_found = m.strip()
                    
                    if args.code:
                        if args.code == code_found:
                             # If searching for specific code, we might want to return JSON or just confirm
                             # But the PHP wrapper now outputs text.
                             # If the user is using the OLD search functionality (via query param 'code'),
                             # the PHP wrapper still does `echo implode("\n", $out);`
                             # So we should probably output consistent text.
                             results.append(f"{filename}: Found {code_found}")
                    else:
                        # Extraction mode: list all codes
                        # Avoid duplicates in output? Or list all occurrences?
                        # The user verification area handles lines of text.
                        # Using "Filename: Code" format might be useful, or just "Code".
                        # User example shows "0320...", just the code. 
                        # But wait, later they say "Asignar".
                        # If I just list codes, how does the system know which file it belongs to?
                        # The user says "Códigos Detectados (Editables)".
                        # It seems they just want a list of codes to be processed/assigned.
                        if code_found not in results:
                            results.append(code_found)
                            
            except Exception as e:
                # Silently fail on bad PDFs to avoid crashing the whole process
                continue
                
    # Output results
    if not results:
        print("No se encontraron códigos.")
    else:
        for r in sorted(results):
            print(r)

if __name__ == "__main__":
    main()