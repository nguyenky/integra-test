<?php
//------------------------------------------------------------------------------
//*** Bosnian/Croatian (hr)
//------------------------------------------------------------------------------
function setLanguageHr(){ 
    $lang['='] = "=";  // "jednako";
    $lang['!='] = "!="; // "not equal"; 
    $lang['>'] = ">";  // "ve&#288;e";
    $lang['>='] = ">=";  // "bigger or equal";
    $lang['<'] = "<";  // "manje";
    $lang['<='] = "<=";  // "smaller or equal";            
    $lang['add'] = "Dodaj";
    $lang['add_new'] = "+ Novi unos";
    $lang['add_new_record'] = "Novi unos dodat";
    $lang['add_new_record_blocked'] = "Sigurnosna provjera: Pokušaj dodavanja novog zapis! Provjerite vaše postavke, operacija nije dozvoljena!";    
    $lang['adding_operation_completed'] = "Dodavajuća operacija uspješno završena!";
    $lang['adding_operation_uncompleted'] = "Dodavajuća operacija nezavršena!";
    $lang['alert_perform_operation'] = "Jeste li sigurni da želite izvršiti ovu operaciju?";
    $lang['alert_select_row'] = "Trebate odabrati jedan ili više redaka za izvršavanje ove operacije!";
    $lang['and'] = "i";
    $lang['any'] = "svaki"; 
    $lang['ascending'] = "Uzlazno"; 
    $lang['back'] = "Natrag";   
    $lang['cancel'] = "Prekinuti";
    $lang['cancel_creating_new_record'] = "Jeste li sigurni da želite odustati od stvaranja novog zapisa?";
    $lang['check_all'] = "Izaberi sve";
    $lang['clear'] = "obriši";
    $lang['click_to_download'] = "Kliknite za download";    
    $lang['clone_selected'] = "Klon odabranih";
    $lang['cloning_record_blocked'] = "Sigurnosnu provjeru: pokušaj kloniranja rekord! Provjerite postavke, operacija nije dopušteno!";
    $lang['cloning_operation_completed'] = "Kloniranje operacija uspješno završena!";
    $lang['cloning_operation_uncompleted'] = "Kloniranje rad nezavršenih!";
    $lang['create'] = "Napravi"; 
    $lang['create_new_record'] = "Novi unos napravit";   
    $lang['current'] = "trenutni";           
    $lang['delete'] = "Briši"; 
    $lang['delete_record'] = "Briši zapis";
    $lang['delete_record_blocked'] = "Sigurnosna provjera: Pokušaj brisanje zapisa! Provjerite vaše postavke, operacija nije dozvoljena!";
    $lang['delete_selected'] = "Obriši";    
    $lang['delete_selected_records'] = "Jeste li sigurni da želite izbrisati odabrane zapise?";
    $lang['delete_this_record'] = "Jeste li sigurni da želite izbrisati ovaj zapis?";        
    $lang['deleting_operation_completed'] = "Operacija brisanja je uspješno završena!";
    $lang['deleting_operation_uncompleted'] = "Operacija brisanja nije potpuna!";
    $lang['descending'] = "Silazno";
    $lang['details'] = "Detalji";
    $lang['details_selected'] = "Prikaži odabrane";
    $lang['download'] = "Preuzimanje";    
    $lang['edit'] = "Uredi";
    $lang['edit_selected'] = "Obradi odabrane";
    $lang['edit_record'] = "Obradi zapis";
    $lang['edit_selected_records'] = "Jeste li sigurni da želite obraditi odabrane zapise?";
    $lang['errors'] = "Pogreške";
    $lang['export_to_excel'] = "Eksport u Excel";
    $lang['export_to_pdf'] = "Eksport u PDF";
    $lang['export_to_word'] = "Eksport u Word";
    $lang['export_to_xml'] = "Eksport u XML";
    $lang['export_message'] = "<label class=\"default_dg_label\">Datoteka _FILE_ je spremna. Nakon završetka downloada ove datoteke,</label> <a class=\"default_dg_error_message\" href=\"javascript: window.close();\"> zatvorite ovaj prozor</a>.";
    $lang['field'] = "Polje"; 
    $lang['field_value'] = "Vrjednost polja";    
    $lang['file_find_error'] = "Nije moguće pronaći datoteku: <b>_FILE_</b>. <br>Provjerite je li datoteka postoji i ovo koristite ispravan put!";
    $lang['file_opening_error'] = "Ne mogu otvoriti datoteku. Provjerite prava datoteke.";
    $lang['file_extension_error'] = "File Upload pogreška: ekstenzija datoteke ne smije za upload. Odaberite neku drugu datoteku.";
    $lang['file_writing_error'] = "Ne mogu pisati u datoteku. Provjerite prava daooteke za pisanje!";
    $lang['file_invalid_file_size'] = "Neispravna veličina datoteke";
    $lang['file_uploading_error'] = "Došlo je do pogreške tijekom učitavanja, molimo pokušajte ponovo!";
    $lang['file_deleting_error'] = "Došlo je do pogreške tijekom brisanja!";
    $lang['first'] = "prvi";
    $lang['format'] = "Format";
    $lang['generate'] = "generiraj";
    $lang['handle_selected_records'] = "Jeste li sigurni da želite upotrijebit odabrane zapise?";
    $lang['hide_search'] = "Sakrij traženjei";
    $lang['item'] = "predmet";
    $lang['items'] = "stavke";
    $lang['last'] = "sadnja";
    $lang['like'] = "kao";
    $lang['like%'] = "kao%";  // "po&#288;inje sa"; 
    $lang['%like'] = "%kao";  // "zavr&#378;ava sa";
    $lang['%like%'] = "%kao%";  
    $lang['loading_data'] = "učitavanje podataka ...";
    $lang['max'] = "max";
    $lang['max_number_of_records'] = "Premašili ste najveći broj zapisa dopušteni!";
    $lang['move_down'] = "Premjesti dolje";
    $lang['move_up'] = "Napredovati";
    $lang['move_operation_completed'] = "Kreće red rad uspješno dovršena!";
    $lang['move_operation_uncompleted'] = "Kreće red rad nezavršenih!";    
    $lang['next'] = "sljedeča";  // slijedeci -> slijedeca !
    $lang['no'] = "Ne";        
    $lang['no_data_found'] = "Nema unosa";
    $lang['no_data_found_error'] = "Nema podataka! Molimte provjerite vašu sintaksu koda!<br>Pripazite mala i velika slova ili specijalne znakove.";
    $lang['no_image'] = "Nema Slike";
    $lang['not_like'] = "not like"; // this should not be translated, if in SQL is used !!!!
    $lang['of'] = "od";
    $lang['operation_was_already_done'] = "Operacija je bila već završena! Vi ne možete je ponovo izvršiti!.";
    $lang['or'] = "ili";        
    $lang['pages'] = "Stranice"; 
    $lang['page_size'] = "Veličina stranice";
    $lang['previous'] = "prethodna";
    $lang['printable_view'] = "Tiskovna verzija";
    $lang['print_now'] = "Želite sad ovu stanicu tiskati?";
    $lang['print_now_title'] = "Kliknite ovdje da biste štampali ovu stranicu";    
    $lang['record_n'] = "Zapis #";
    $lang['refresh_page'] = "Obnovi stranicu";
    $lang['remove'] = "Ukloniti";
    $lang['reset'] = "Reset";
    $lang['results'] = "Rezultati";
    $lang['required_fields_msg'] = "<span style='color:#cd0000'>*</span> Stavke označene sa zvjezdicom su obavezna";    
    $lang['search'] = "Traži"; 
    $lang['search_d'] = "Traženje"; 
    $lang['search_type'] = "Filter"; 
    $lang['select'] = "odaberite";
    $lang['set_date'] = "Postavi datum";
    $lang['sort'] = "Sortiranje";    
    $lang['test'] = "Test";
    $lang['total'] = "Ukupno";
    $lang['turn_on_debug_mode'] = "Za više informacija, uključite debug mod.";
    $lang['uncheck_all'] = "Isključi sve";
    $lang['unhide_search'] = "Otkrij Pretraga";
    $lang['unique_field_error'] = "Polje _FIELD_ omogućava samo jedinstvene vrijednosti - molim opet ući!";
    $lang['update'] = "Ažurirati";    
    $lang['update_record'] = "Ažuriranje zapisa";
    $lang['update_record_blocked'] = "Security Check: pokušaj ažuriranje rekord! Provjerite vaše postavke, operacija nije dozvoljena!";    
    $lang['updating_operation_completed'] = "Modernizacija operacija uspješno završena!";
    $lang['updating_operation_uncompleted'] = "Modernizacija rada nezavršena!";
    $lang['upload'] = "Učitavanje";
    $lang['uploaded_file_not_image'] = "Učitana datoteka nije slika.";
    $lang['view'] = "Pogled";    
    $lang['view_details'] = "Pogledaj ";
    $lang['warnings'] = "Upozorenja";
    $lang['with_selected'] = "S odabranim";
    $lang['wrong_field_name'] = "Pogrešan naziv polja";
    $lang['wrong_parameter_error'] = "Pogrešan parametar u [<b>_FIELD_</b>]: _VALUE_";
    $lang['yes'] = "Da";       

    // date-time
    $lang['day']    = "dan";
    $lang['month']  = "mjesec";
    $lang['year']   = "godina";
    $lang['hour']   = "sat";
    $lang['min']    = "min";
    $lang['sec']    = "sek";
    $lang['months'][1] = "Siječanj";
    $lang['months'][2] = "Veljača";
    $lang['months'][3] = "Ožujak";
    $lang['months'][4] = "Travanj";
    $lang['months'][5] = "Svibanj";
    $lang['months'][6] = "Lipanj";
    $lang['months'][7] = "Srpanj";
    $lang['months'][8] = "Kolovoz";
    $lang['months'][9] = "Rujan";
    $lang['months'][10] = "Listopad";
    $lang['months'][11] = "Studeni";
    $lang['months'][12] = "Prosinac";
        
    return $lang; 
}
?>