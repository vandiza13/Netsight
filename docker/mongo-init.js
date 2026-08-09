db = db.getSiblingDB('genieacs');
db.configs.updateOne(
    { _id: "cwmp.connectionRequestAuth" },
    { $set: { value: 'AUTH("netsight", "netsight123")' } },
    { upsert: true }
);
