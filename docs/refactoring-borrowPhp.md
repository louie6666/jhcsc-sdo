1. new system flow when someone wants to borrow, first the staff search first the id or name of the borrower using the search bar on the headr so make that search bar functioning that it can search the ids or name in the borrower table then it will hightlight it matched, wherever page it is in the pagination it will automatically open on that page, the hightlight wont disappear then dont use API for it.

2. refactor the table change the titles/fields to this: id number, borrower name, items borrowed, borrowed date, due date, cellphone number, actions.

a. id number= the id number
b. borrower name= this will display the borrower fullname + the department so sample louie chua bsit 3d
c. items borrowed= this column it will display all the items borrowed, theres a fix width in this column so if the name wont fit it will go to next line so this row will expand downward, sample the borrowed items are: basketball, net, rocket, then gloves, if gloves wont fit it will go to next row or next line so this row of the specific borrower will expand depending on how many items borrowed.
d. borrowed dat= it will show the date in this format (m-d-y)
due date= same it will show like this (m-d-y)
cellphone number= displays the cp number
e. actions= this buttons are return and add, then rework how the conditions good or damaged into this, each equipemnt borrowed in the list in the right side there is a check box, with also 8px radius, heres how it works all those boxes that are checked it means the condition is good, so that equipment will go back to available and then those boxes that are NOT check it means that equipment is damaged so it will automatically throw to the maintenance table or how the maintenance table get that list, then rework the return button to this, if you click the return button theres an echo the message will say did you already check the condtitions by checking the box it means ok if not its damage? then if ok is click, another echo say "are you sure to continue the transactions?" this will prevent from misclicking the return button.

3. the + new transaction button= remove this cheker of the id number now the staff needs to check the id first before proceeding, now remove that the because the flow is changed, the staff first search the name or id number through search bar in the header, if no match found, that message should be visible please add that, thats the time the staff will open this new transaction button

4. reword the add button= if that is clicked this modal in the new transaction will pop up, so make another modal on this put here modals/add_equipment.php thats the file path, modal pops up when +add button is clicked then all the borrowed equipments will be read only now, then you can search what to add then confirm transaction, after that the list of equipments borrowed should be added. heres how the equipment list should look exapmle- basketball + checkbox rightside, another equipment list + checkbox, and so on, thats how it looks in the row

5. dont touch the pagination, row limits = 14