var osa = require("osa2");


osa(function (name, folderId) {
    // Create an object for accessing Notes
    var Notes = Application("Notes");
    // Search inside a specific folder
    var folder = Notes.folders.byId(folderId);
    // Find a note by it's name
    var notes = folder.notes.where({
        name: name,
    });
    // Was it found?
    if (!notes.length) {
        throw new Error("Note " + name + " note found");
    }
    return {
        body: notes[0].body(),
        creationDate: notes[0].creationDate(),
        id: notes[0].id(),
        modificationDate: notes[0].modificationDate(),
        name: notes[0].name(),
    };
})(name, folderId).then(note => console.log(note));
